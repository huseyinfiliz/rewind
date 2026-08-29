@extends('rewind::layout')

@section('title', $year . ' Community Rewind - ' . ($forumTitle ?? 'Forum'))

@section('content')
@php
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $peakMonthNum = (int) ($metrics['busiest_month']['peak_month'] ?? 0);
    $peakMonthName = ($peakMonthNum >= 1 && $peakMonthNum <= 12) ? $monthNames[$peakMonthNum] : null;
    $peakHour = $metrics['peak_hour']['peak_hour'] ?? null;
@endphp

<section class="rw-hero">
    <div class="rw-hero-tag">
        <i class="fas fa-users"></i> Community Annual Highlights
    </div>
    <h1 class="rw-hero-title">{{ $year }} Forum Rewind</h1>
    <p class="rw-hero-subtitle">Together, we built connections, shared knowledge, and celebrated milestones across {{ $forumTitle ?? 'our community' }}.</p>
</section>

<!-- Community Key Numbers -->
<div class="rw-grid rw-grid-4">
    <!-- New Users -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--purple">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['new_users']['count'] ?? 0) }}</div>
        <div class="rw-card-label">New Members</div>
        <div class="rw-card-desc">Welcomed into our growing community this year.</div>
    </div>

    <!-- Total Posts -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--pink">
            <i class="fas fa-comment-dots"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['total_posts']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Total Posts</div>
        <div class="rw-card-desc">Messages, answers, and contributions shared.</div>
    </div>

    <!-- Total Discussions -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--amber">
            <i class="fas fa-comments"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['total_discussions']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Discussions Started</div>
        <div class="rw-card-desc">Engaging conversations initiated across tags.</div>
    </div>

    <!-- Total Words -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--cyan">
            <i class="fas fa-book-open"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['total_words']['total_words'] ?? 0) }}</div>
        <div class="rw-card-label">Words Exchanged</div>
        <div class="rw-card-desc">
            @if(isset($metrics['total_words']['average_per_post']))
                Avg. {{ round($metrics['total_words']['average_per_post'], 1) }} words per post.
            @else
                Ideas documented and preserved.
            @endif
        </div>
    </div>
</div>

<!-- Community Pulse & Highlights -->
<div class="rw-grid rw-grid-2">
    <!-- Busiest Time & Pulse -->
    <div class="rw-card rw-card--highlight">
        <div class="rw-card-icon rw-card-icon--emerald">
            <i class="fas fa-fire"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 12px;">Forum Rhythm</h3>

        @if($peakMonthName)
            <div style="margin-bottom: 16px;">
                <div style="font-size: 0.85rem; color: var(--rw-text-muted);">Busiest Month</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #34d399;">
                    {{ $peakMonthName }}
                    @if(isset($metrics['busiest_month']['post_count']))
                        <span style="font-size: 0.9rem; font-weight: 500; color: var(--rw-text-muted);">({{ number_format($metrics['busiest_month']['post_count']) }} posts)</span>
                    @endif
                </div>
            </div>
        @endif

        @if($peakHour !== null)
            <div>
                <div style="font-size: 0.85rem; color: var(--rw-text-muted);">Most Active Hour of the Day</div>
                <div style="font-size: 1.2rem; font-weight: 700;">
                    <i class="fas fa-clock" style="color: #fbbf24;"></i> {{ sprintf('%02d:00 - %02d:00', $peakHour, ($peakHour + 1) % 24) }} UTC
                    @if(isset($metrics['peak_hour']['post_count']))
                        <span style="font-size: 0.85rem; color: var(--rw-text-muted); font-weight: normal;">({{ number_format($metrics['peak_hour']['post_count']) }} posts)</span>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Top Space / Tag -->
    @if(!empty($metrics['top_tag']['name']))
        <div class="rw-card">
            <div class="rw-card-icon rw-card-icon--purple">
                <i class="fas fa-tag"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">Most Popular Topic</h3>
            <div class="rw-card-desc" style="margin-bottom: 14px;">The topic that drew the most discussion this year.</div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="rw-pill" style="font-size: 1.1rem; padding: 8px 20px; background: {{ $metrics['top_tag']['color'] ?? 'var(--rw-primary)' }}; color: #fff; font-weight: 700;">
                    # {{ $metrics['top_tag']['name'] }}
                </span>
                @if(isset($metrics['top_tag']['count']))
                    <span style="font-size: 0.9rem; color: var(--rw-text-muted);">
                        {{ number_format($metrics['top_tag']['count']) }} posts
                    </span>
                @endif
            </div>
        </div>
    @elseif(!empty($metrics['top_discussion']['title']))
        <div class="rw-card">
            <div class="rw-card-icon rw-card-icon--pink">
                <i class="fas fa-bolt"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">Hot Discussion</h3>
            <p style="font-weight: 600; font-size: 1.1rem; color: #fff; margin-top: 10px;">
                {{ $metrics['top_discussion']['title'] }}
            </p>
            @if(isset($metrics['top_discussion']['post_count']))
                <div class="rw-card-desc" style="margin-top: 6px;">{{ $metrics['top_discussion']['post_count'] }} replies</div>
            @endif
        </div>
    @endif
</div>

<!-- Standout Discussions & Members -->
@if(!empty($metrics['top_discussion']['title']) || !empty($metrics['most_active_user']['username']) || !empty($metrics['most_loved']['username']))
    <h3 class="rw-section-title">
        <i class="fas fa-trophy" style="color: var(--rw-amber);"></i> Standout Highlights
    </h3>
    <div class="rw-grid rw-grid-3">
        <!-- Most Active Member -->
        @if(!empty($metrics['most_active_user']['username']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--amber">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="rw-card-label">Most Active Contributor</div>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 10px;">
                    @if(!empty($metrics['most_active_user']['avatar_url']))
                        <img src="{{ $metrics['most_active_user']['avatar_url'] }}" alt="{{ $metrics['most_active_user']['username'] }}" class="rw-avatar" style="width: 48px; height: 48px;">
                    @else
                        <div class="rw-avatar" style="width: 48px; height: 48px; background: var(--rw-gradient-hero); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff;">
                            {{ strtoupper(substr($metrics['most_active_user']['username'], 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                            {{ $metrics['most_active_user']['display_name'] ?? $metrics['most_active_user']['username'] }}
                        </div>
                        <div style="font-size: 0.85rem; color: var(--rw-text-muted);">
                            {{ number_format($metrics['most_active_user']['post_count'] ?? 0) }} posts
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Hot Discussion -->
        @if(!empty($metrics['top_discussion']['title']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--pink">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="rw-card-label">Biggest Discussion</div>
                <div style="margin-top: 10px;">
                    <div style="font-weight: 700; font-size: 1.05rem; color: #fff; line-height: 1.3;">
                        {{ $metrics['top_discussion']['title'] }}
                    </div>
                    @if(!empty($metrics['top_discussion']['post_count']))
                        <div style="font-size: 0.85rem; color: var(--rw-text-muted); margin-top: 6px;">
                            {{ number_format($metrics['top_discussion']['post_count']) }} replies
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Most Loved Member -->
        @if(!empty($metrics['most_loved']['username']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--pink">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="rw-card-label">Most Loved Member</div>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 10px;">
                    @if(!empty($metrics['most_loved']['avatar_url']))
                        <img src="{{ $metrics['most_loved']['avatar_url'] }}" alt="{{ $metrics['most_loved']['username'] }}" class="rw-avatar" style="width: 48px; height: 48px;">
                    @else
                        <div class="rw-avatar" style="width: 48px; height: 48px; background: var(--rw-gradient-hero); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff;">
                            {{ strtoupper(substr($metrics['most_loved']['username'], 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">
                            {{ $metrics['most_loved']['display_name'] ?? $metrics['most_loved']['username'] }}
                        </div>
                        <div style="font-size: 0.85rem; color: #f472b6;">
                            {{ number_format($metrics['most_loved']['likes_received'] ?? 0) }} likes received ❤️
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif

<!-- Top Contributors List -->
@if(!empty($metrics['top_contributors']['contributors']))
    <h3 class="rw-section-title">
        <i class="fas fa-medal" style="color: var(--rw-primary);"></i> Top Contributors
    </h3>
    <div class="rw-grid rw-grid-3">
        @foreach(array_slice($metrics['top_contributors']['contributors'], 0, 6) as $index => $c)
            <div class="rw-card" style="padding: 18px 22px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="font-size: 1.25rem; font-weight: 800; color: {{ $index === 0 ? '#fbbf24' : ($index === 1 ? '#94a3b8' : ($index === 2 ? '#b45309' : 'var(--rw-text-muted)')) }}; width: 24px; text-align: center;">
                        #{{ $index + 1 }}
                    </div>
                    @if(!empty($c['avatar_url']))
                        <img src="{{ $c['avatar_url'] }}" alt="{{ $c['username'] ?? '' }}" class="rw-avatar" style="width: 42px; height: 42px;">
                    @else
                        <div class="rw-avatar" style="width: 42px; height: 42px; background: var(--rw-gradient-hero); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: #fff;">
                            {{ strtoupper(substr($c['username'] ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ $c['display_name'] ?? $c['username'] ?? 'Member' }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--rw-text-muted);">
                            {{ number_format($c['post_count'] ?? 0) }} posts
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- Leaderboards (Best Answers & Badges) -->
@if(!empty($metrics['best_answers_leaderboard']['leaders']) || !empty($metrics['badge_leaderboard']['leaders']))
    <h3 class="rw-section-title">
        <i class="fas fa-award" style="color: var(--rw-emerald);"></i> Community Hall of Fame
    </h3>
    <div class="rw-grid rw-grid-2">
        @if(!empty($metrics['best_answers_leaderboard']['leaders']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--emerald">
                    <i class="fas fa-check-double"></i>
                </div>
                <h4 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 14px;">Best Answers Leaderboard</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @foreach(array_slice($metrics['best_answers_leaderboard']['leaders'], 0, 5) as $idx => $l)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--rw-card-border);">
                            <span style="font-weight: 600;">#{{ $idx + 1 }} @ {{ $l['username'] }}</span>
                            <span class="rw-pill rw-pill--success">{{ $l['count'] }} solutions</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($metrics['badge_leaderboard']['leaders']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--amber">
                    <i class="fas fa-shield-halved"></i>
                </div>
                <h4 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 14px;">Badge Leaderboard</h4>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @foreach(array_slice($metrics['badge_leaderboard']['leaders'], 0, 5) as $idx => $b)
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--rw-card-border);">
                            <span style="font-weight: 600;">#{{ $idx + 1 }} @ {{ $b['username'] }}</span>
                            <span class="rw-pill" style="color: #fbbf24;"><i class="fas fa-medal"></i> {{ $b['count'] }} badges</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif

<!-- Call to action -->
<div class="rw-card rw-card--highlight" style="text-align: center; padding: 40px 20px; margin-top: 40px;">
    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 10px;">Want your own personalized recap?</h3>
    <p style="color: var(--rw-text-muted); max-width: 500px; margin: 0 auto 20px;">
        Discover your personal posting stats, favorite spaces, activity rhythm, and best friend on the forum.
    </p>
    <a href="{{ ($baseUrl ?? '') . '/rewind' }}" class="rw-btn rw-btn-primary">
        <i class="fas fa-user"></i> View My Personal Rewind
    </a>
</div>
@endsection
