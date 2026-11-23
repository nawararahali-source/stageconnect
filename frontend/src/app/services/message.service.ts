import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { AuthService } from './auth.service';

export interface ConversationMessage {
  id: number;
  conversation_id: number;
  sender_id: number;
  body: string;
  read_at: string | null;
  created_at: string;
  sender?: {
    id: number;
    first_name: string;
    last_name: string;
  };
}

export interface MessageConversation {
  id: number;
  application_id: number;
  other_participant: {
    id: number;
    name: string;
  };
  offer_title: string;
  last_message: ConversationMessage | null;
  unread_count: number;
  updated_at: string;
}

interface ConversationListResponse {
  success: boolean;
  conversations: MessageConversation[];
}

interface MessagesResponse {
  success: boolean;
  messages: {
    data: ConversationMessage[];
    current_page: number;
    last_page: number;
  };
}

interface SingleMessageResponse {
  success: boolean;
  message: ConversationMessage;
}

interface StartConversationResponse {
  success: boolean;
  conversation: { id: number };
}

interface UnreadCountResponse {
  success: boolean;
  unread_count: number;
}

@Injectable({ providedIn: 'root' })
export class MessageService {
  private readonly apiUrl = 'http://localhost:8000/api';

  constructor(private http: HttpClient, private authService: AuthService) {}

  private get options() {
    return {
      headers: {
        Authorization: `Bearer ${this.authService.getToken() || ''}`
      }
    };
  }

  getConversations(): Observable<ConversationListResponse> {
    return this.http.get<ConversationListResponse>(`${this.apiUrl}/conversations`, this.options);
  }

  getMessages(conversationId: number): Observable<MessagesResponse> {
    return this.http.get<MessagesResponse>(
      `${this.apiUrl}/conversations/${conversationId}/messages`,
      this.options
    );
  }

  sendMessage(conversationId: number, body: string): Observable<SingleMessageResponse> {
    return this.http.post<SingleMessageResponse>(
      `${this.apiUrl}/conversations/${conversationId}/messages`,
      { body },
      this.options
    );
  }

  startConversation(applicationId: number): Observable<StartConversationResponse> {
    return this.http.post<StartConversationResponse>(
      `${this.apiUrl}/applications/${applicationId}/conversation`,
      {},
      this.options
    );
  }

  getUnreadCount(): Observable<UnreadCountResponse> {
    return this.http.get<UnreadCountResponse>(`${this.apiUrl}/messages/unread-count`, this.options);
  }
}
