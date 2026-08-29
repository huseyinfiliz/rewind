@extends('rewind::layout')

@section('title', $user->display_name . "'s " . $year . ' Rewind')

@section('content')
@php
    $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    $peakMonthNum = (int) ($metrics['most_active_month']['peak_month'] ?? 0);
    $peakMonthName = ($peakMonthNum >= 1 && $peakMonthNum <= 12) ? $monthNames[$peakMonthNum] : null;
    $isNightOwl = (bool) ($metrics['night_owl']['is_night_owl'] ?? false);
    $peakHour = $metrics['night_owl']['peak_hour'] ?? null;
@endphp

<section class="rw-hero">
    <div class="rw-hero-tag">
        <i class="fas fa-sparkles"></i> Personalized Annual Recap
    </div>
    <h1 class="rw-hero-title">{{ $year }} in Review</h1>
    <p class="rw-hero-subtitle">A celebration of your journey, conversations, and impact across the community.</p>
</section>

<!-- User Profile Card -->
<div class="rw-user-header">
    @if(!empty($user->avatar_url))
        <img src="{{ $user->avatar_url }}" alt="{{ $user->username }}" class="rw-avatar">
    @else
        <div class="rw-avatar" style="background: var(--rw-gradient-hero); display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; color: #fff;">
            {{ strtoupper(substr($user->username, 0, 1)) }}
        </div>
    @endif
    <div style="flex: 1;">
        <h2 style="font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 4px;">
            {{ $user->display_name }}
        </h2>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
            <span class="rw-pill"><i class="fas fa-at"></i> {{ $user->username }}</span>
            @if($snapshot->is_public)
                <span class="rw-pill rw-pill--success"><i class="fas fa-globe-americas"></i> Public Recap</span>
            @else
                <span class="rw-pill"><i class="fas fa-lock"></i> Private Recap</span>
            @endif
            @if(!empty($snapshot->generated_at))
                <span class="rw-pill"><i class="fas fa-calendar-alt"></i> {{ $snapshot->generated_at->format('M d, Y') }}</span>
            @endif
        </div>
    </div>
</div>

<!-- Primary Activity Grid -->
<div class="rw-grid rw-grid-4">
    <!-- Posts -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--purple">
            <i class="fas fa-pen-fancy"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['post_count']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Posts Written</div>
        <div class="rw-card-desc">
            @if(isset($metrics['_community_avg']['posts']) && $metrics['_community_avg']['posts'] > 0)
                @php $ratio = ($metrics['post_count']['count'] ?? 0) / $metrics['_community_avg']['posts']; @endphp
                @if($ratio >= 1.5)
                    <span style="color: #34d399;"><i class="fas fa-arrow-trend-up"></i> {{ round($ratio, 1) }}x above community avg</span>
                @else
                    <span>Community avg: {{ round($metrics['_community_avg']['posts'], 1) }}</span>
                @endif
            @else
                Every contribution shaped the forum's story.
            @endif
        </div>
    </div>

    <!-- Discussions -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--pink">
            <i class="fas fa-comments"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['discussion_count']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Discussions Started</div>
        <div class="rw-card-desc">
            @if(isset($metrics['_community_avg']['discussions']) && $metrics['_community_avg']['discussions'] > 0)
                <span>Community avg: {{ round($metrics['_community_avg']['discussions'], 1) }}</span>
            @else
                New threads and ideas sparked by you.
            @endif
        </div>
    </div>

    <!-- Active Days -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--amber">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['active_days']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Days Active</div>
        <div class="rw-card-desc">
            @php $daysPercent = round((($metrics['active_days']['count'] ?? 0) / 365) * 100); @endphp
            Present for {{ $daysPercent }}% of the whole year.
        </div>
    </div>

    <!-- Words Written -->
    <div class="rw-card">
        <div class="rw-card-icon rw-card-icon--cyan">
            <i class="fas fa-book-open"></i>
        </div>
        <div class="rw-card-value">{{ number_format($metrics['word_count']['count'] ?? 0) }}</div>
        <div class="rw-card-label">Total Words</div>
        <div class="rw-card-desc">
            @php $readingMinutes = ceil(($metrics['word_count']['count'] ?? 0) / 200); @endphp
            Roughly {{ $readingMinutes }} min of reading material.
        </div>
    </div>
</div>

<!-- Rhythm & Highlights -->
<div class="rw-grid rw-grid-2">
    <!-- Peak Activity & Rhythm -->
    <div class="rw-card rw-card--highlight">
        <div class="rw-card-icon rw-card-icon--emerald">
            <i class="fas fa-chart-line"></i>
        </div>
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 12px;">Your Activity Rhythm</h3>
        
        @if($peakMonthName)
            <div style="margin-bottom: 16px;">
                <div style="font-size: 0.85rem; color: var(--rw-text-muted);">Busiest Month</div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #34d399;">
                    <i class="fas fa-fire"></i> {{ $peakMonthName }}
                    <span style="font-size: 0.9rem; font-weight: 500; color: var(--rw-text-muted);">({{ number_format($metrics['most_active_month']['peak_count'] ?? 0) }} posts)</span>
                </div>
            </div>
        @endif

        @if($peakHour !== null)
            <div>
                <div style="font-size: 0.85rem; color: var(--rw-text-muted);">Peak Hour</div>
                <div style="font-size: 1.2rem; font-weight: 700;">
                    @if($isNightOwl)
                        <i class="fas fa-moon" style="color: #c084fc;"></i> Night Owl (Peak at {{ sprintf('%02d:00', $peakHour) }})
                    @else
                        <i class="fas fa-sun" style="color: #fbbf24;"></i> Early Bird / Daytime (Peak at {{ sprintf('%02d:00', $peakHour) }})
                    @endif
                </div>
            </div>
        @endif
    </div>

    <!-- Favorite Category / Top Tag -->
    @if(!empty($metrics['top_tag']['tag_name']))
        <div class="rw-card">
            <div class="rw-card-icon rw-card-icon--purple">
                <i class="fas fa-tag"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">Favorite Space</h3>
            <div class="rw-card-desc" style="margin-bottom: 14px;">Where you spent the most time engaging.</div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span class="rw-pill" style="font-size: 1rem; padding: 8px 18px; background: {{ $metrics['top_tag']['tag_color'] ?? 'var(--rw-primary)' }}; color: #fff; font-weight: 700;">
                    # {{ $metrics['top_tag']['tag_name'] }}
                </span>
                <span style="font-size: 0.9rem; color: var(--rw-text-muted);">
                    {{ number_format($metrics['top_tag']['count'] ?? 0) }} posts
                </span>
            </div>
        </div>
    @elseif(!empty($metrics['best_post']['post_id']))
        <div class="rw-card">
            <div class="rw-card-icon rw-card-icon--amber">
                <i class="fas fa-trophy"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 6px;">Star Contribution</h3>
            <div class="rw-card-desc">Your most engaging moment of {{ $year }}.</div>
            @if(!empty($metrics['best_post']['discussion_title']))
                <p style="margin-top: 10px; font-weight: 600; color: #fff;">
                    In: {{ $metrics['best_post']['discussion_title'] }}
                </p>
            @endif
        </div>
    @endif
</div>

<!-- Highlighted Post -->
@if(!empty($metrics['best_post']['post_id']) && !empty($metrics['best_post']['content_html']))
    <div class="rw-card" style="margin-bottom: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div class="rw-card-icon rw-card-icon--pink" style="width: 36px; height: 36px; margin-bottom: 0; font-size: 1rem;">
                    <i class="fas fa-heart"></i>
                </div>
                <h3 style="font-size: 1.15rem; font-weight: 700;">Most Loved Post of the Year</h3>
            </div>
            @if(!empty($metrics['best_post']['count']))
                <span class="rw-pill rw-pill--success"><i class="fas fa-thumbs-up"></i> {{ $metrics['best_post']['count'] }} reactions</span>
            @endif
        </div>

        <div class="rw-quote-box">
            {!! $metrics['best_post']['content_html'] !!}
        </div>

        @if(!empty($metrics['best_post']['discussion_title']))
            <div style="font-size: 0.85rem; color: var(--rw-text-muted); text-align: right;">
                Thread: <strong>{{ $metrics['best_post']['discussion_title'] }}</strong>
            </div>
        @endif
    </div>
@endif

<!-- Social & Community Connections -->
@if(!empty($metrics['best_friend']['username']) || !empty($metrics['likes_given']['count']) || !empty($metrics['likes_received']['count']) || !empty($metrics['best_answers']['count']) || !empty($metrics['badges_earned']['count']))
    <h3 class="rw-section-title">
        <i class="fas fa-user-group" style="color: var(--rw-secondary);"></i> Community & Connections
    </h3>
    <div class="rw-grid rw-grid-3">
        <!-- Best Friend / Frequent Collaborator -->
        @if(!empty($metrics['best_friend']['username']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--pink">
                    <i class="fas fa-handshake"></i>
                </div>
                <div class="rw-card-label">Top Collaborator</div>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 10px;">
                    @if(!empty($metrics['best_friend']['avatar_url']))
                        <img src="{{ $metrics['best_friend']['avatar_url'] }}" alt="{{ $metrics['best_friend']['username'] }}" class="rw-avatar" style="width: 48px; height: 48px;">
                    @else
                        <div class="rw-avatar" style="width: 48px; height: 48px; background: var(--rw-gradient-hero); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff;">
                            {{ strtoupper(substr($metrics['best_friend']['username'], 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #fff;">{{ $metrics['best_friend']['display_name'] ?? $metrics['best_friend']['username'] }}</div>
                        <div style="font-size: 0.8rem; color: var(--rw-text-muted);">{{ $metrics['best_friend']['interaction_count'] ?? 0 }} mutual interactions</div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Likes Received & Given -->
        @if(isset($metrics['likes_received']['count']) || isset($metrics['likes_given']['count']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--purple">
                    <i class="fas fa-heart"></i>
                </div>
                <div class="rw-card-label">Appreciation & Reactions</div>
                <div style="margin-top: 8px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: var(--rw-text-muted);">Received:</span>
                        <strong style="color: #f472b6;">{{ number_format($metrics['likes_received']['count'] ?? 0) }} ❤️</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--rw-text-muted);">Given:</span>
                        <strong style="color: #a5b4fc;">{{ number_format($metrics['likes_given']['count'] ?? 0) }} 👍</strong>
                    </div>
                </div>
            </div>
        @endif

        <!-- Badges & Best Answers -->
        @if(!empty($metrics['best_answers']['count']) || !empty($metrics['badges_earned']['count']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--amber">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="rw-card-label">Achievements</div>
                <div style="margin-top: 8px;">
                    @if(isset($metrics['best_answers']['count']) && $metrics['best_answers']['count'] > 0)
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: var(--rw-text-muted);">Best Answers:</span>
                            <strong style="color: #34d399;">{{ $metrics['best_answers']['count'] }} 🏆</strong>
                        </div>
                    @endif
                    @if(isset($metrics['badges_earned']['count']) && $metrics['badges_earned']['count'] > 0)
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: var(--rw-text-muted);">Badges Unlocked:</span>
                            <strong style="color: #fbbf24;">{{ $metrics['badges_earned']['count'] }} 🎖️</strong>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endif

<!-- Top Vocabulary & Emojis -->
@if(!empty($metrics['top_words']['words']) || !empty($metrics['top_emojis']['emojis']))
    <h3 class="rw-section-title">
        <i class="fas fa-signature" style="color: var(--rw-cyan);"></i> Signature Style
    </h3>
    <div class="rw-grid rw-grid-2">
        @if(!empty($metrics['top_words']['words']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--cyan">
                    <i class="fas fa-quote-right"></i>
                </div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 6px;">Top Words</h4>
                <div class="rw-card-desc">Your most frequently used terms this year.</div>
                <div class="rw-tag-cloud">
                    @foreach(array_slice($metrics['top_words']['words'], 0, 12) as $w)
                        <span class="rw-tag-item">
                            {{ $w['word'] ?? $w }}
                            @if(isset($w['count']))
                                <small style="color: var(--rw-text-muted); font-size: 0.75rem;">x{{ $w['count'] }}</small>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($metrics['top_emojis']['emojis']))
            <div class="rw-card">
                <div class="rw-card-icon rw-card-icon--amber">
                    <i class="fas fa-face-smile"></i>
                </div>
                <h4 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 6px;">Top Emojis</h4>
                <div class="rw-card-desc">Your favorite expressions.</div>
                <div class="rw-tag-cloud">
                    @foreach(array_slice($metrics['top_emojis']['emojis'], 0, 10) as $e)
                        <span class="rw-tag-item" style="font-size: 1.25rem;">
                            {{ $e['emoji'] ?? $e }}
                            @if(isset($e['count']))
                                <small style="font-size: 0.75rem; color: var(--rw-text-muted);">x{{ $e['count'] }}</small>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif

<!-- Call to action / Community banner -->
<div class="rw-card rw-card--highlight" style="text-align: center; padding: 40px 20px; margin-top: 40px;">
    <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 10px;">Want to see how the whole forum did?</h3>
    <p style="color: var(--rw-text-muted); max-width: 500px; margin: 0 auto 20px;">
        Explore the {{ $year }} Community Highlights to see total discussions, top discussions, and group milestones.
    </p>
    <a href="{{ ($baseUrl ?? '') . '/rewind/view/' . $year }}" class="rw-btn rw-btn-primary">
        <i class="fas fa-users"></i> View {{ $year }} Community Recap
    </a>
</div>
@endsection
