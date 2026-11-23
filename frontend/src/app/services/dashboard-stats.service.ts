import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AuthService } from './auth.service';

export type ApplicationStatus = 'pending' | 'accepted' | 'rejected';

export interface ApplicationStatusCounts {
  pending: number;
  accepted: number;
  rejected: number;
}

export interface CompanyDashboardStats {
  success: boolean;
  applications_by_status: ApplicationStatusCounts;
  applications_by_offer: Array<{
    offer_id: number;
    offer_title: string;
    count: number;
  }>;
  score_distribution: Array<{
    label: string;
    min: number;
    max: number;
    count: number;
  }>;
}

export interface StudentDashboardStats {
  success: boolean;
  applications_by_status: ApplicationStatusCounts;
}

@Injectable({ providedIn: 'root' })
export class DashboardStatsService {
  private readonly apiUrl = 'http://localhost:8000/api';

  constructor(
    private http: HttpClient,
    private authService: AuthService
  ) {}

  getCompanyStats(): Observable<CompanyDashboardStats> {
    return this.http.get<CompanyDashboardStats>(
      `${this.apiUrl}/company/stats`,
      { headers: this.headers }
    );
  }

  getStudentStats(): Observable<StudentDashboardStats> {
    return this.http.get<StudentDashboardStats>(
      `${this.apiUrl}/student/stats`,
      { headers: this.headers }
    );
  }

  private get headers(): HttpHeaders {
    const token = this.authService.getToken();
    return token
      ? new HttpHeaders({ Authorization: `Bearer ${token}` })
      : new HttpHeaders();
  }
}
