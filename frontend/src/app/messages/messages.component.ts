import { Component, ElementRef, OnDestroy, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { EMPTY, Subscription, of, timer } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';
import { AuthService } from '../services/auth.service';
import {
  ConversationMessage,
  MessageConversation,
  MessageService
} from '../services/message.service';
import { SidebarComponent } from '../shared/sidebar.component';

interface MessagesUser {
  id: number;
  first_name: string;
  last_name: string;
  role: string;
}

@Component({
  selector: 'app-messages',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, SidebarComponent],
  templateUrl: './messages.component.html',
  styleUrl: './messages.component.css'
})
export class MessagesComponent implements OnInit, OnDestroy {
  @ViewChild('messageList') private messageList?: ElementRef<HTMLElement>;

  user: MessagesUser | null = null;
  conversations: MessageConversation[] = [];
  messages: ConversationMessage[] = [];
  selectedConversationId: number | null = null;
  draft = '';
  errorMessage = '';
  isLoading = true;
  isSending = false;
  isSidebarCollapsed = false;
  isConversationListVisible = true;
  private pollingSubscription?: Subscription;
  private querySubscription?: Subscription;
  private preferredConversationId: number | null = null;
  private lastMessageId: number | null = null;

  constructor(
    private authService: AuthService,
    private messageService: MessageService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.isSidebarCollapsed = window.innerWidth <= 1024;
    if (!this.authService.isAuthenticated()) {
      this.router.navigate(['/'], { replaceUrl: true });
      return;
    }

    this.user = this.authService.getUser();
    this.querySubscription = this.route.queryParams.subscribe((params) => {
      const id = Number(params['conversation']);
      this.preferredConversationId = Number.isInteger(id) && id > 0 ? id : null;
      if (this.conversations.length) {
        this.selectPreferredConversation();
      }
    });

    this.pollingSubscription = timer(0, 5000).pipe(
      switchMap(() => this.messageService.getConversations().pipe(
        switchMap((response) => {
          this.conversations = response.conversations || [];
          this.selectPreferredConversation();
          const conversationId = this.selectedConversationId;
          if (conversationId === null) {
            return of({ conversationId: null, messages: [] as ConversationMessage[] });
          }
          return this.messageService.getMessages(conversationId).pipe(
            map((messageResponse) => ({
              conversationId,
              messages: [...(messageResponse.messages?.data || [])].reverse()
            }))
          );
        }),
        catchError((error) => {
          this.handleError(error, 'Impossible de charger vos conversations.');
          this.isLoading = false;
          return EMPTY;
        })
      ))
    ).subscribe(({ conversationId, messages }) => {
      if (conversationId !== this.selectedConversationId) {
        return;
      }
      this.setMessages(messages);
      this.errorMessage = '';
      this.isLoading = false;
    });
  }

  ngOnDestroy(): void {
    this.pollingSubscription?.unsubscribe();
    this.querySubscription?.unsubscribe();
  }

  get fullName(): string {
    return [this.user?.first_name, this.user?.last_name].filter(Boolean).join(' ');
  }

  get isStudent(): boolean {
    return this.user?.role === 'etudiant';
  }

  get isCompany(): boolean {
    return this.user?.role === 'entreprise';
  }

  get selectedConversation(): MessageConversation | null {
    return this.conversations.find((conversation) => conversation.id === this.selectedConversationId) || null;
  }

  selectConversation(conversation: MessageConversation): void {
    this.selectedConversationId = conversation.id;
    this.preferredConversationId = conversation.id;
    this.isConversationListVisible = false;
    this.lastMessageId = null;
    this.messages = [];
    this.errorMessage = '';
    this.loadMessages(conversation.id);
  }

  backToConversations(): void {
    this.isConversationListVisible = true;
  }

  sendMessage(): void {
    const body = this.draft.trim();
    const conversationId = this.selectedConversationId;
    if (!body || !conversationId || this.isSending || body.length > 2000) {
      return;
    }

    this.isSending = true;
    this.errorMessage = '';
    this.messageService.sendMessage(conversationId, body).subscribe({
      next: (response) => {
        this.draft = '';
        this.isSending = false;
        this.setMessages([...this.messages, response.message]);
        this.refreshConversations();
      },
      error: (error) => {
        this.isSending = false;
        this.handleError(error, 'Impossible d’envoyer ce message.');
      }
    });
  }

  onComposerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.sendMessage();
    }
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.finishLogout(),
      error: (error) => {
        console.error('Logout error:', error);
        this.finishLogout();
      }
    });
  }

  private refreshConversations(): void {
    this.messageService.getConversations().subscribe({
      next: (response) => {
        this.conversations = response.conversations || [];
      },
      error: (error) => this.handleError(error, 'Impossible d’actualiser les conversations.')
    });
  }

  private loadMessages(conversationId: number): void {
    this.messageService.getMessages(conversationId).subscribe({
      next: (response) => {
        if (conversationId === this.selectedConversationId) {
          this.setMessages([...(response.messages?.data || [])].reverse());
        }
      },
      error: (error) => this.handleError(error, 'Impossible de charger les messages.')
    });
  }

  private setMessages(messages: ConversationMessage[]): void {
    const latestId = messages[messages.length - 1]?.id ?? null;
    this.messages = messages;
    if (latestId !== this.lastMessageId) {
      this.lastMessageId = latestId;
      setTimeout(() => this.scrollToLatest(), 0);
    }
  }

  private scrollToLatest(): void {
    const element = this.messageList?.nativeElement;
    if (element) {
      element.scrollTop = element.scrollHeight;
    }
  }

  private selectPreferredConversation(): void {
    const preferred = this.preferredConversationId
      ? this.conversations.find((conversation) => conversation.id === this.preferredConversationId)
      : null;
    const currentExists = this.conversations.some(
      (conversation) => conversation.id === this.selectedConversationId
    );
    if (preferred) {
      this.selectedConversationId = preferred.id;
      this.isConversationListVisible = false;
    } else if (!currentExists) {
      this.selectedConversationId = this.conversations[0]?.id ?? null;
    }
  }

  private handleError(error: { status?: number }, fallback: string): void {
    console.error(fallback, error);
    if (error.status === 401) {
      this.authService.clearToken();
      this.router.navigate(['/'], { replaceUrl: true });
      return;
    }
    this.errorMessage = error.status === 403
      ? 'Vous n’avez pas accès à cette conversation.'
      : fallback;
  }

  private finishLogout(): void {
    this.authService.clearToken();
    this.router.navigate(['/'], { replaceUrl: true });
  }
}
