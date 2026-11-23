import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface InternshipOffer {
  id: number;
  title: string;
  domain: string;
  description: string;
  duration: string;
  location: string;
  work_mode: 'onsite' | 'hybrid' | 'remote';
  required_skills: string[] | null;
  positions: number;
  accepted_applications_count: number;
  available_positions: number;
  deadline: string | null;
  status: 'open' | 'closed';
  company?: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    role: string;
  };
}

export interface InternshipOfferData {
  title: string;
  domain: string;
  description: string;
  duration: string;
  location: string;
  work_mode: 'onsite' | 'hybrid' | 'remote';
  required_skills: string[];
  positions: number;
  deadline: string | null;
  status: 'open' | 'closed';
}

interface InternshipOfferResponse {
  success: boolean;
  message: string;
  offers?: InternshipOffer[];
  offer?: InternshipOffer;
  errors?: Record<string, string[]>;
}

export interface CoverLetterResponse {
  success: boolean;
  message: string;
  cover_letter?: string;
}

@Injectable({
  providedIn: 'root'
})
export class InternshipOfferService {
  private apiRoot = 'http://localhost:8000/api';
  private apiUrl = `${this.apiRoot}/company/offers`;

  constructor(private http: HttpClient) {}

  private get headers() {
    return { Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}` };
  }

  getOffers(): Observable<InternshipOfferResponse> {
    return this.http.get<InternshipOfferResponse>(this.apiUrl, { headers: this.headers });
  }

  createOffer(data: InternshipOfferData): Observable<InternshipOfferResponse> {
    return this.http.post<InternshipOfferResponse>(this.apiUrl, data, { headers: this.headers });
  }

  updateOffer(id: number, data: InternshipOfferData): Observable<InternshipOfferResponse> {
    return this.http.put<InternshipOfferResponse>(`${this.apiUrl}/${id}`, data, { headers: this.headers });
  }

  deleteOffer(id: number): Observable<InternshipOfferResponse> {
    return this.http.delete<InternshipOfferResponse>(`${this.apiUrl}/${id}`, { headers: this.headers });
  }

  generateCoverLetter(offerId: number): Observable<CoverLetterResponse> {
    return this.http.post<CoverLetterResponse>(
      `${this.apiRoot}/offers/${offerId}/cover-letter`,
      {},
      { headers: this.headers }
    );
  }
}
