import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-loading-skeleton',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './loading-skeleton.component.html',
  styleUrl: './loading-skeleton.component.css'
})
export class LoadingSkeletonComponent {
  @Input() type: 'text' | 'avatar' | 'card' | 'list' = 'text';
  @Input() width = '100%';
  @Input() height = '20px';
  @Input() count = 1;
  @Input() rounded = true;
}
