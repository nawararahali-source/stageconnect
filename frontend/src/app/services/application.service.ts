import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { InternshipOffer } from './internship-offer.service';

export interface StudentCv {
  id: number;
  file_name: string;
  download_url?: string;
  file_path?: string;
}

export interface Application {
  id: number;
  student_id: number;
  internship_offer_id: number | string;
  status: 'pending' | 'accepted' | 'rejected';
  applied_at: string;
  created_at: string;
  updated_at: string;
  internshipOffer?: InternshipOffer;
  student?: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    profile?: {
      formation?: string;
      specialite?: string;
      niveau?: string;
      bio?: string;
      telephone?: string;
      ville?: string;
    };
    cvs?: StudentCv[];
  };
}

export interface ApplicationApiResponse {
  success: boolean;
  message: string;
  offers?: InternshipOffer[];
  applications?: Application[];
  application?: Application;
  errors?: Record<string, string[]>;
}

interface StatusUpdateRequest {
  status: 'accepted' | 'rejected';
}

@Injectable({
  providedIn: 'root'
})
export class ApplicationService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get headers() {
    return { Authorization: `Bearer ${localStorage.getItem('auth_token') || ''}` };
  }

  // Student: Get all open offers
  getOffers(): Observable<ApplicationApiResponse> {
    return this.http.get<ApplicationApiResponse>(`${this.apiUrl}/student/offers`, { headers: this.headers });
  }

  // Student: Apply to an offer
  applyToOffer(offerId: number): Observable<ApplicationApiResponse> {
    return this.http.post<ApplicationApiResponse>(`${this.apiUrl}/student/offers/${offerId}/apply`, {}, { headers: this.headers });
  }

  // Student: Get my applications
  getMyApplications(): Observable<ApplicationApiResponse> {
    return this.http.get<ApplicationApiResponse>(`${this.apiUrl}/student/applications`, { headers: this.headers });
  }

  // Company: Get received applications
  getCompanyApplications(): Observable<ApplicationApiResponse> {
    return this.http.get<ApplicationApiResponse>(`${this.apiUrl}/company/applications`, { headers: this.headers });
  }

  // Company: Update application status
  updateApplicationStatus(applicationId: number, status: StatusUpdateRequest['status']): Observable<ApplicationApiResponse> {
    return this.http.patch<ApplicationApiResponse>(
      `${this.apiUrl}/company/applications/${applicationId}/status`,
      { status },
      { headers: this.headers }
    );
  }

  getCvDownloadUrl(cv: StudentCv): string {
    if (cv.download_url) {
      return cv.download_url;
    }

    if (!cv.file_path) {
      return '';
    }

    const filePath = cv.file_path.split('/').map(encodeURIComponent).join('/');
    return `${this.apiUrl.replace(/\/api$/, '')}/storage/${filePath}`;
  }
}
