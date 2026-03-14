import AsyncStorage from '@react-native-async-storage/async-storage';
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';

const API_BASE_URL = 'http://10.152.172.154:8001/api';
const TOKEN_KEY = 'school_api_token';

export type ApiUser = {
  id: number;
  role: string;
  email: string;
  name: string;
};

export type LoginResponse = {
  message: string;
  token: string;
  token_type: string;
  user: ApiUser;
};

export type ProfileUpdateInput = {
  name?: string;
  phone?: string;
  address?: string;
  bio?: string;
  image?: {
    uri: string;
    type?: string;
    name?: string;
  };
};

class SchoolApiService {
  private client: AxiosInstance;

  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        Accept: 'application/json',
      },
    });

    this.client.interceptors.request.use(async (config) => {
      const token = await this.getToken();
      if (token) {
        config.headers = config.headers ?? {};
        config.headers.Authorization = `Bearer ${token}`;
      }
      return config;
    });
  }

  async login(login: string, password: string, deviceName = 'react-native-app'): Promise<LoginResponse> {
    const response = await this.client.post<LoginResponse>('/auth/login', {
      login,
      password,
      device_name: deviceName,
    });

    await this.setToken(response.data.token);
    return response.data;
  }

  async logout(): Promise<void> {
    await this.client.post('/auth/logout');
    await this.clearToken();
  }

  async getMe(): Promise<{ user: ApiUser }> {
    const response = await this.client.get('/auth/me');
    return response.data;
  }

  async getDashboardSummary(): Promise<any> {
    const response = await this.client.get('/dashboard/summary');
    return response.data;
  }

  async getClasses(config?: AxiosRequestConfig): Promise<any> {
    const response = await this.client.get('/classes', config);
    return response.data;
  }

  async getAnnouncements(config?: AxiosRequestConfig): Promise<any> {
    const response = await this.client.get('/announcements', config);
    return response.data;
  }

  async getNotifications(config?: AxiosRequestConfig): Promise<any> {
    const response = await this.client.get('/notifications', config);
    return response.data;
  }

  async getMessages(config?: AxiosRequestConfig): Promise<any> {
    const response = await this.client.get('/messages', config);
    return response.data;
  }

  async updateProfile(input: ProfileUpdateInput): Promise<any> {
    if (!input.image) {
      const response = await this.client.patch('/auth/profile', {
        name: input.name,
        phone: input.phone,
        address: input.address,
        bio: input.bio,
      });

      return response.data;
    }

    const formData = new FormData();

    if (input.name) formData.append('name', input.name);
    if (input.phone) formData.append('phone', input.phone);
    if (input.address) formData.append('address', input.address);
    if (input.bio) formData.append('bio', input.bio);

    formData.append('image', {
      uri: input.image.uri,
      type: input.image.type ?? 'image/jpeg',
      name: input.image.name ?? 'profile.jpg',
    } as any);

    const response = await this.client.patch('/auth/profile', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    return response.data;
  }

  async setToken(token: string): Promise<void> {
    await AsyncStorage.setItem(TOKEN_KEY, token);
  }

  async getToken(): Promise<string | null> {
    return AsyncStorage.getItem(TOKEN_KEY);
  }

  async clearToken(): Promise<void> {
    await AsyncStorage.removeItem(TOKEN_KEY);
  }
}

export const schoolApi = new SchoolApiService();
export default schoolApi;
