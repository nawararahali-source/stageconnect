import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ProfileAnalysis {
  resume: string;
  points_forts: string[];
  competences_maitrisees: string[];
  competences_a_ameliorer: string[];
  recommandations: string[];
  raw_content?: string;
}

interface AnalysisResponse {
  success: boolean;
  message: string;
  analysis?: ProfileAnalysis;
  errors?: any;
}

@Injectable({
  providedIn: 'root'
})
export class ProfileAnalysisService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get token(): string | null {
    return localStorage.getItem('auth_token');
  }

  analyzeProfile(): Observable<AnalysisResponse> {
    return this.http.post<AnalysisResponse>(`${this.apiUrl}/profile/analyze`, {}, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }
}
