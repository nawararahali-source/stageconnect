import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface MatchingSkill {
  name: string;
  level: string;
  required: string;
}

export interface MatchingContribution {
  valeur: number;
  poids_pourcent: number;
  contribution: number;
}

export interface MatchingResult {
  application_id: number | string;
  student_id: number;
  student_name: string;
  score: number;
  matching_skills: MatchingSkill[];
  missing_skills: string[];
  explanation: string[];
  source: 'ml' | 'rules';
  contributions: Record<string, MatchingContribution>;
  criteria: {
    skills: { weight: number; applicable: boolean; score: number };
    domain: { weight: number; applicable: boolean; score: number };
    projects_experiences: { weight: number; applicable: boolean; score: number };
    education_level: { weight: number; applicable: boolean; score: number };
    location: { weight: number; applicable: boolean; score: number };
  };
}

export interface MatchingResponse {
  success: boolean;
  offer_id: number;
  offer_title: string;
  matching_results: MatchingResult[];
}

@Injectable({
  providedIn: 'root'
})
export class MatchingService {
  private apiUrl = 'http://127.0.0.1:8000/api';

  constructor(private http: HttpClient) {}

  private get headers() {
    return { Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}` };
  }

  getOfferMatching(offerId: number): Observable<MatchingResponse> {
    return this.http.get<MatchingResponse>(
      `${this.apiUrl}/company/offers/${offerId}/matching`,
      { headers: this.headers }
    );
  }
}
