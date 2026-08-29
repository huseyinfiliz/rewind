@extends('rewind::layout')

@section('title', ($errorTitle ?? 'Notice') . ' - ' . ($forumTitle ?? 'Forum'))

@section('content')
<div style="max-width: 600px; margin: 60px auto; text-align: center;">
    <div class="rw-card" style="padding: 48px 32px;">
        <div class="rw-card-icon rw-card-icon--pink" style="width: 64px; height: 64px; font-size: 1.75rem; margin: 0 auto 24px;">
            @if(($statusCode ?? 200) === 403)
                <i class="fas fa-lock"></i>
            @elseif(($statusCode ?? 200) === 404)
                <i class="fas fa-ghost"></i>
            @else
                <i class="fas fa-exclamation-triangle"></i>
            @endif
        </div>
        
        <h2 style="font-size: 1.75rem; font-weight: 800; margin-bottom: 12px; letter-spacing: -0.02em;">
            {{ $errorTitle ?? 'Notice' }}
        </h2>
        
        <p style="color: var(--rw-text-muted); font-size: 1.05rem; line-height: 1.6; margin-bottom: 30px;">
            {{ $errorMessage ?? 'This recap is not currently available.' }}
        </p>

        <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
            <a href="{{ ($baseUrl ?? '') . '/rewind' }}" class="rw-btn rw-btn-primary">
                <i class="fas fa-history"></i> Return to Rewind
            </a>
            <a href="{{ $baseUrl ?? '/' }}" class="rw-btn">
                <i class="fas fa-home"></i> Forum Home
            </a>
        </div>
    </div>
</div>
@endsection
