import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface RegisterData {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'etudiant' | 'entreprise';
}

interface LoginData {
  email: string;
  password: string;
}

interface AuthResponse {
  success: boolean;
  message: string;
  user: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    role: string;
  };
  token?: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  // API URL - Backend Laravel sur port 8000
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get token(): string | null {
    return localStorage.getItem('auth_token');
  }

  register(data: RegisterData): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/register`, data);
  }

  login(data: LoginData): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, data);
  }

  logout(): Observable<any> {
    return this.http.post(`${this.apiUrl}/logout`, {}, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  me(): Observable<AuthResponse> {
    return this.http.get<AuthResponse>(`${this.apiUrl}/me`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  setToken(token: string): void {
    localStorage.setItem('auth_token', token);
  }

  setUser(user: any): void {
    localStorage.setItem('auth_user', JSON.stringify(user));
  }

  getUser(): any {
    const userStr = localStorage.getItem('auth_user');
    return userStr ? JSON.parse(userStr) : null;
  }

  getToken(): string | null {
    return this.token;
  }

  clearToken(): void {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
  }

  isAuthenticated(): boolean {
    return !!this.token;
  }
}
