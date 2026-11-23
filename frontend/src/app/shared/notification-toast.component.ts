import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-notification-toast',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './notification-toast.component.html',
  styleUrl: './notification-toast.component.css'
})
export class NotificationToastComponent {
  @Input() show = false;
  @Input() type: 'success' | 'error' | 'warning' | 'info' = 'info';
  @Input() title = '';
  @Input() message = '';
  @Input() duration = 5000;
  @Output() close = new EventEmitter<void>();

  private timeoutId: any;

  ngOnChanges() {
    if (this.show) {
      this.autoClose();
    }
  }

  autoClose() {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
    }
    this.timeoutId = setTimeout(() => {
      this.close.emit();
    }, this.duration);
  }

  onClose() {
    if (this.timeoutId) {
      clearTimeout(this.timeoutId);
    }
    this.close.emit();
  }

  get icon(): string {
    const icons = {
      success: '✓',
      error: '✕',
      warning: '⚠',
      info: 'ℹ'
    };
    return icons[this.type];
  }

  get bgColor(): string {
    const colors = {
      success: 'var(--color-success-light)',
      error: 'var(--color-danger-light)',
      warning: 'var(--color-warning-light)',
      info: 'var(--color-info-light)'
    };
    return colors[this.type];
  }

  get borderColor(): string {
    const colors = {
      success: 'var(--color-success)',
      error: 'var(--color-danger)',
      warning: 'var(--color-warning)',
      info: 'var(--color-info)'
    };
    return colors[this.type];
  }

  get textColor(): string {
    const colors = {
      success: 'var(--color-success)',
      error: 'var(--color-danger)',
      warning: 'var(--color-warning)',
      info: 'var(--color-info)'
    };
    return colors[this.type];
  }
}
