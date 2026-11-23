import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface SkillData {
  name: string;
  level: 'débutant' | 'intermédiaire' | 'avancé';
}

interface SkillResponse {
  success: boolean;
  message: string;
  skill?: any;
  skills?: any[];
  errors?: any;
}

@Injectable({
  providedIn: 'root'
})
export class SkillService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get token(): string | null {
    return localStorage.getItem('auth_token');
  }

  getSkills(): Observable<SkillResponse> {
    return this.http.get<SkillResponse>(`${this.apiUrl}/skills`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  createSkill(data: SkillData): Observable<SkillResponse> {
    return this.http.post<SkillResponse>(`${this.apiUrl}/skills`, data, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  updateSkill(id: number, data: SkillData): Observable<SkillResponse> {
    return this.http.put<SkillResponse>(`${this.apiUrl}/skills/${id}`, data, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  deleteSkill(id: number): Observable<SkillResponse> {
    return this.http.delete<SkillResponse>(`${this.apiUrl}/skills/${id}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }
}
