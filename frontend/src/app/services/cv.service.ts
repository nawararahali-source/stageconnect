import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

interface CvResponse {
  success: boolean;
  message: string;
  cv?: any;
  cvs?: any[];
  errors?: any;
}

@Injectable({
  providedIn: 'root'
})
export class CvService {
  private apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient) {}

  private get token(): string | null {
    return localStorage.getItem('auth_token');
  }

  getCvs(): Observable<CvResponse> {
    return this.http.get<CvResponse>(`${this.apiUrl}/cv`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  uploadCv(file: File): Observable<CvResponse> {
    const formData = new FormData();
    formData.append('cv', file);

    return this.http.post<CvResponse>(`${this.apiUrl}/cv`, formData, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }

  deleteCv(id: number): Observable<CvResponse> {
    return this.http.delete<CvResponse>(`${this.apiUrl}/cv/${id}`, {
      headers: {
        'Authorization': `Bearer ${this.token}`
      }
    });
  }
}
