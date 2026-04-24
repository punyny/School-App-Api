<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithInternalApi;
use App\Services\InternalApiClient;
use App\Support\PasswordRule;
use App\Support\ProfileImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use InteractsWithInternalApi;

    public function show(Request $request, InternalApiClient $api): View|RedirectResponse
    {
        $result = $api->get($request, '/api/auth/me');
        if ($result['status'] !== 200) {
            return redirect()->away(route('dashboard', [], false))->withErrors($this->extractErrors($result));
        }

        $userData = is_array($result['data']['user'] ?? null)
            ? $result['data']['user']
            : [];

        return view('web.profile.show', [
            'userData' => $userData,
            'insights' => $this->buildRoleInsights($request, $api, $userData),
        ]);
    }

    public function generateTelegramLinkCode(Request $request, InternalApiClient $api): JsonResponse
    {
        $result = $api->post($request, '/api/integrations/telegram/link-code');
        $status = (int) ($result['status'] ?? 500);
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];

        if ($status < 200 || $status >= 300) {
            return response()->json([
                'ok' => false,
                'message' => (string) ($data['message'] ?? 'Unable to generate Telegram link code.'),
                'errors' => $data['errors'] ?? null,
            ], $status >= 400 ? $status : 500);
        }

        return response()->json([
            'ok' => true,
            'message' => (string) ($data['message'] ?? 'Telegram link code generated.'),
            'data' => is_array($data['data'] ?? null) ? $data['data'] : null,
        ], $status);
    }

    public function update(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image' => ProfileImageStorage::uploadValidationRules(),
            'remove_image' => ['nullable', 'boolean'],
        ]);

        if (($payload['remove_image'] ?? false) === true) {
            $payload['image_url'] = null;
        }
        if (! $request->hasFile('image')) {
            unset($payload['image']);
        }
        unset($payload['remove_image']);

        $result = $api->patch($request, '/api/auth/profile', $payload);
        if ($result['status'] !== 200) {
            return back()->withInput()->withErrors($this->extractErrors($result));
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request, InternalApiClient $api): RedirectResponse
    {
        $payload = $request->validate([
            'new_password' => ['required', 'confirmed', PasswordRule::defaults()],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        $result = $api->post($request, '/api/auth/change-password', $payload);
        if ($result['status'] !== 200) {
            return back()->withErrors($this->extractErrors($result));
        }

        // The API revokes previous tokens after password change, so force a clean token on the next request.
        $request->session()->forget(['web_api_token', 'web_api_token_id']);

        return back()->with('success', 'Password changed successfully.');
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function buildRoleInsights(Request $request, InternalApiClient $api, array $userData): array
    {
        $role = $this->normalizeRole((string) ($userData['role'] ?? ''));

        if ($role === 'teacher') {
            return $this->buildTeacherInsights($request, $api, $userData);
        }

        if ($role === 'student') {
            return $this->buildStudentInsights($request, $api, $userData);
        }

        if ($role === 'parent') {
            return $this->buildParentInsights($request, $api, $userData);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function buildTeacherInsights(Request $request, InternalApiClient $api, array $userData): array
    {
        $teacherId = (int) ($userData['id'] ?? 0);
        $classItems = $this->extractCollectionItems($api->get($request, '/api/classes', ['per_page' => 50]));

        $classCards = collect($classItems)
            ->map(function (array $item): array {
                $classId = (int) ($item['id'] ?? 0);

                return [
                    'id' => $classId,
                    'name' => (string) ($item['name'] ?? ('Class '.$classId)),
                    'grade_level' => (string) ($item['grade_level'] ?? ''),
                    'room' => (string) ($item['room'] ?? ''),
                    'students_count' => (int) ($item['students_count'] ?? 0),
                    'subjects_count' => (int) ($item['subjects_count'] ?? 0),
                ];
            })
            ->filter(fn (array $item): bool => $item['id'] > 0)
            ->sortBy('name')
            ->values();

        $studentMap = [];
        foreach ($classCards->take(8) as $classCard) {
            $classResult = $api->get($request, '/api/classes/'.$classCard['id']);
            if (($classResult['status'] ?? 0) !== 200) {
                continue;
            }

            $classData = $classResult['data']['data'] ?? null;
            if (! is_array($classData)) {
                continue;
            }

            $className = trim((string) ($classData['name'] ?? $classCard['name']));
            $students = $classData['students'] ?? [];
            if (! is_array($students)) {
                continue;
            }

            foreach ($students as $student) {
                if (! is_array($student)) {
                    continue;
                }

                $studentId = (int) ($student['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $studentMap[$studentId] = [
                    'id' => $studentId,
                    'name' => (string) ($student['user']['name'] ?? ('Student '.$studentId)),
                    'class_name' => $className !== '' ? $className : '-',
                    'grade' => (string) ($student['grade'] ?? ''),
                ];
            }
        }

        $myTimetable = collect(
            $this->extractCollectionItems($api->get($request, '/api/timetables', [
                'teacher_id' => $teacherId > 0 ? $teacherId : null,
                'per_page' => 100,
            ]))
        )
            ->map(function (array $row): array {
                $dayRaw = strtolower((string) ($row['day_of_week'] ?? ''));
                $dayOrder = [
                    'monday' => 1,
                    'tuesday' => 2,
                    'wednesday' => 3,
                    'thursday' => 4,
                    'friday' => 5,
                    'saturday' => 6,
                    'sunday' => 7,
                ];

                return [
                    'day' => ucfirst($dayRaw !== '' ? $dayRaw : '-'),
                    'day_order' => $dayOrder[$dayRaw] ?? 99,
                    'time_start' => mb_substr((string) ($row['time_start'] ?? '-'), 0, 5),
                    'time_end' => mb_substr((string) ($row['time_end'] ?? '-'), 0, 5),
                    'class_name' => (string) ($row['class']['name'] ?? '-'),
                    'subject_name' => (string) ($row['subject']['name'] ?? '-'),
                ];
            })
            ->sortBy([
                ['day_order', 'asc'],
                ['time_start', 'asc'],
            ])
            ->values();

        return [
            'classes' => $classCards->all(),
            'students' => collect(array_values($studentMap))
                ->sortBy('name')
                ->values()
                ->all(),
            'timetable' => $myTimetable->all(),
            'stats' => [
                'classes_count' => $classCards->count(),
                'students_count' => count($studentMap),
                'timetable_count' => $myTimetable->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function buildStudentInsights(Request $request, InternalApiClient $api, array $userData): array
    {
        $studentProfile = is_array($userData['student_profile'] ?? null)
            ? $userData['student_profile']
            : [];
        $studentId = (int) ($studentProfile['id'] ?? 0);
        $classId = (int) ($studentProfile['class_id'] ?? 0);

        $classData = [];
        if ($classId > 0) {
            $classResult = $api->get($request, '/api/classes/'.$classId);
            if (($classResult['status'] ?? 0) === 200 && is_array($classResult['data']['data'] ?? null)) {
                $classData = $classResult['data']['data'];
            }
        }

        $recentScores = collect(
            $this->extractCollectionItems($api->get($request, '/api/scores', [
                'student_id' => $studentId > 0 ? $studentId : null,
                'per_page' => 30,
                'sort_by' => 'created_at',
                'sort_dir' => 'desc',
            ]))
        )
            ->map(function (array $score): array {
                return [
                    'subject_name' => (string) ($score['subject']['name'] ?? '-'),
                    'assessment_type' => (string) ($score['assessment_type'] ?? '-'),
                    'month' => $score['month'] ?? null,
                    'semester' => $score['semester'] ?? null,
                    'total_score' => (float) ($score['total_score'] ?? 0),
                    'grade' => (string) ($score['grade'] ?? '-'),
                    'rank_in_class' => $score['rank_in_class'] ?? null,
                ];
            })
            ->take(12)
            ->values();

        $parents = collect($studentProfile['parents'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'name' => (string) ($item['name'] ?? '-'),
                'phone' => (string) ($item['phone'] ?? ''),
            ])
            ->values();

        $subjects = collect($classData['subjects'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'name' => (string) ($item['name'] ?? '-'),
                'full_score' => (float) ($item['full_score'] ?? 100),
            ])
            ->values();

        return [
            'student_profile' => $studentProfile,
            'class_data' => $classData,
            'parents' => $parents->all(),
            'subjects' => $subjects->all(),
            'recent_scores' => $recentScores->all(),
            'stats' => [
                'subjects_count' => $subjects->count(),
                'scores_count' => $recentScores->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    private function buildParentInsights(Request $request, InternalApiClient $api, array $userData): array
    {
        $children = collect($userData['children'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        $childCards = $children->map(function (array $child) use ($request, $api): array {
            $studentId = (int) ($child['id'] ?? 0);
            $scores = $this->extractCollectionItems($api->get($request, '/api/scores', [
                'student_id' => $studentId > 0 ? $studentId : null,
                'per_page' => 60,
            ]));

            $scoreValues = collect($scores)
                ->map(fn (array $score): float => (float) ($score['total_score'] ?? 0))
                ->values();
            $latestRank = collect($scores)
                ->pluck('rank_in_class')
                ->first(fn ($value): bool => $value !== null && $value !== '');

            return [
                'id' => $studentId,
                'name' => (string) ($child['user']['name'] ?? ('Student '.$studentId)),
                'class_name' => (string) ($child['class']['name'] ?? '-'),
                'grade' => (string) ($child['grade'] ?? ''),
                'score_average' => $scoreValues->count() > 0
                    ? round($scoreValues->sum() / $scoreValues->count(), 2)
                    : null,
                'score_count' => $scoreValues->count(),
                'latest_rank' => $latestRank,
            ];
        })->values();

        return [
            'children' => $childCards->all(),
            'stats' => [
                'children_count' => $childCards->count(),
                'with_score_count' => $childCards->filter(fn (array $child): bool => (int) $child['score_count'] > 0)->count(),
            ],
        ];
    }

    private function normalizeRole(string $role): string
    {
        $value = strtolower(trim($role));

        return $value === 'guardian' ? 'parent' : $value;
    }

    /**
     * @param  array{status:int, data:array<string,mixed>|null}  $result
     * @return array<int, array<string, mixed>>
     */
    private function extractCollectionItems(array $result): array
    {
        if (($result['status'] ?? 0) !== 200) {
            return [];
        }

        $payload = $result['data'] ?? [];
        if (! is_array($payload)) {
            return [];
        }

        $items = $payload['data'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values()
            ->all();
    }
}
