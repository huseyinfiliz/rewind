import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import extractText from 'flarum/common/utils/extractText';
import { animate, stagger, createTimeline } from 'animejs';
import { animateClock } from '../animators/clock';
import type Mithril from 'mithril';
import ensureCommunityRewind from '../utils/ensureCommunityRewind';
import { getYearRenderMode } from '../../common/utils/getYearRenderMode';

// Çevirilerdeki HTML (VNode) etiketlerini bozmadan döndürmek için
function trans(key: string, params?: Record<string, any>): any {
  return params ? app.translator.trans(key, params) : app.translator.trans(key);
}

const MONTH_NAMES = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTH_NAMES_FULL = [
  '',
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

const SLIDE_TRANSITION_MS = 450;

function utcToLocalHour(utcHour: number): number {
  const offsetMinutes = new Date().getTimezoneOffset();
  return Math.round((utcHour - offsetMinutes / 60 + 24) % 24) % 24;
}

function shiftHourCounts(hourCounts: number[]): number[] {
  const shifted = new Array(24).fill(0);
  for (let utcH = 0; utcH < 24; utcH++) {
    shifted[utcToLocalHour(utcH)] = hourCounts[utcH] || 0;
  }
  return shifted;
}

type Slide = { type: string; key?: string; data?: any };

export default class CommunitySlideshow extends Page {
  loading = true;
  snapshot: any = null;
  data: Record<string, any> | null = null;
  prevData: Record<string, any> | null = null;
  currentSlide = 0;
  direction = 1;
  animating = false;
  slides: Slide[] = [];
  touchStartX = 0;
  isDestroyed = false;
  preparingCommunity = false;

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    const year = this.getYear();
    app.setTitle(`${year} Community Rewind`);
    this.loadSnapshot();
  }

  getYear(): number {
    const routeYear = m.route.param('year');
    return routeYear ? parseInt(routeYear, 10) : app.forum.attribute<number>('rewindActiveYear');
  }

  async loadSnapshot() {
    this.loading = true;
    m.redraw();
    const year = this.getYear();
    try {
      const response = await app.store.find('rw-community', { filter: { year } } as any);
      const results = Array.isArray(response) ? response : [response];
      this.snapshot = results[0] || null;
      if (!this.snapshot) {
        this.preparingCommunity = true;
        m.redraw();
        if (await ensureCommunityRewind(year, () => this.isDestroyed)) {
          if (getYearRenderMode(year) === 'blade') {
            window.location.reload();
            return;
          }
          const ready = await app.store.find('rw-community', { filter: { year } } as any);
          this.snapshot = (Array.isArray(ready) ? ready[0] : ready) || null;
        }
        this.preparingCommunity = false;
      }
      this.data = this.snapshot?.snapshotData() || null;

      try {
        const prevResponse = await app.store.find('rw-community', { filter: { year: year - 1 } } as any);
        const prevResults = Array.isArray(prevResponse) ? prevResponse : [prevResponse];
        this.prevData = prevResults[0]?.snapshotData() || null;
      } catch {
        this.prevData = null;
      }

      if (this.data) {
        const hiddenRaw = app.forum.attribute<string>('rewindHiddenCommunitySlides') || '[]';
        let hidden: string[] = [];
        try {
          hidden = JSON.parse(hiddenRaw);
        } catch {
          hidden = [];
        }

        const canShow = (key: string, check: boolean) => !hidden.includes(key) && check;

        const metricSlides: Slide[] = [];

        if (canShow('year-overview', !!(this.data.total_posts?.count || this.data.total_discussions?.count || this.data.new_users?.count))) {
          metricSlides.push({ type: 'year-overview' });
        }
        if (canShow('busiest_month', !!(this.data.busiest_month?.peak_count > 0))) metricSlides.push({ type: 'metric', key: 'busiest_month' });
        if (canShow('peak_hour', !!(this.data.peak_hour?.peak_count > 0))) metricSlides.push({ type: 'metric', key: 'peak_hour' });
        if (canShow('top_contributors', this.data.top_contributors?.users?.length > 0))
          metricSlides.push({ type: 'metric', key: 'top_contributors' });
        if (canShow('star_post', !!this.data.star_post?.post_id)) metricSlides.push({ type: 'metric', key: 'star_post' });
        if (canShow('most_loved', this.data.most_loved?.users?.length > 0)) metricSlides.push({ type: 'metric', key: 'most_loved' });
        if (canShow('top_discussion', !!this.data.top_discussion?.id)) metricSlides.push({ type: 'metric', key: 'top_discussion' });
        if (canShow('top_tag', !!this.data.top_tag?.id)) metricSlides.push({ type: 'metric', key: 'top_tag' });
        if (canShow('total_words', !!this.data.total_words?.total_words)) metricSlides.push({ type: 'metric', key: 'total_words' });
        if (canShow('new_members', this.data.new_members_list?.recent?.length > 0)) metricSlides.push({ type: 'metric', key: 'new_members' });
        if (canShow('best_answers_leaderboard', this.data.best_answers_leaderboard?.users?.length > 0))
          metricSlides.push({ type: 'metric', key: 'best_answers_leaderboard' });
        if (canShow('badge_leaderboard', this.data.badge_leaderboard?.users?.length > 0))
          metricSlides.push({ type: 'metric', key: 'badge_leaderboard' });

        if (metricSlides.length === 0) {
          this.slides = [{ type: 'empty' }];
        } else {
          this.slides = [{ type: 'intro' }, ...metricSlides, { type: 'summary' }];
        }

        const userIds: number[] = [];
        this.data.top_contributors?.users?.forEach((u: any) => userIds.push(u.user_id));
        this.data.most_loved?.users?.forEach((u: any) => userIds.push(u.user_id));
        if (this.data.star_post?.user_id) userIds.push(this.data.star_post.user_id);
        if (this.data.top_discussion?.author_id) userIds.push(this.data.top_discussion.author_id);
        if (this.data.most_active_user?.id) userIds.push(this.data.most_active_user.id);
        this.data.new_members_list?.recent?.forEach((u: any) => userIds.push(u.user_id));
        this.data.best_answers_leaderboard?.users?.forEach((u: any) => userIds.push(u.user_id));
        this.data.badge_leaderboard?.users?.forEach((u: any) => userIds.push(u.user_id));

        const unique = [...new Set(userIds)].filter((id) => id && !app.store.getById('users', String(id)));

        if (unique.length > 0) {
          Promise.all(unique.map((uid) => app.store.find('users', String(uid)).catch(() => null))).then(() => {
            if (!this.isDestroyed) m.redraw();
          });
        }
      }
    } catch (e) {
      console.error('CommunitySlideshow: failed to load', e);
    }
    this.loading = false;
    if (!this.isDestroyed) m.redraw();
  }

  oncreate(vnode: Mithril.VnodeDOM) {
    super.oncreate(vnode);
    this.isDestroyed = false;
    document.body.classList.add('RewindSlideshowPage-active');
    document.addEventListener('keydown', this.onKeyDown);
  }

  onremove(vnode: Mithril.VnodeDOM) {
    super.onremove(vnode);
    this.isDestroyed = true;
    document.body.classList.remove('RewindSlideshowPage-active');
    document.removeEventListener('keydown', this.onKeyDown);
  }

  onKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape') this.goBack();
    else if (e.key === 'ArrowRight') this.next();
    else if (e.key === 'ArrowLeft') this.prev();
  };

  onupdate(vnode: Mithril.VnodeDOM) {
    super.onupdate(vnode);
    this.runAnimations(vnode.dom as Element);
  }

  private lastAnimatedSlide = -1;

  runAnimations(root: Element) {
    if (this.loading || this.lastAnimatedSlide === this.currentSlide) return;
    this.lastAnimatedSlide = this.currentSlide;
    const slide = this.slides[this.currentSlide];
    if (!slide) return;

    if (slide.type === 'intro') {
      this.animateIntroSlide(root);
      return;
    }
    if (slide.type === 'summary') {
      this.animateSummarySlide(root);
      return;
    }

    this.animateHeader(root);

    switch (slide.key) {
      case 'busiest_month':
        this.animateChartSlide(root);
        break;
      case 'peak_hour':
        this.animateClockSlide(root);
        break;
      case 'top_contributors':
      case 'most_loved':
      case 'best_answers_leaderboard':
      case 'badge_leaderboard':
        this.animateUserListSlide(root);
        break;
      case 'star_post':
      case 'top_discussion':
        this.animatePreviewSlide(root);
        break;
      case 'top_tag':
        this.animateSpotlightSlide(root);
        break;
      case 'new_members':
        this.animateAvatarGridSlide(root);
        break;
      default:
        this.animateCountUpSlide(root);
        break;
    }
  }

  animateHeader(root: Element) {
    const header = root.querySelector('.rw-slide-header');
    if (!header) return;
    const label = header.querySelector('.rw-slide-label');
    const desc = header.querySelector('.rw-slide-desc');
    if (label) animate(label, { opacity: [0, 1], translateY: [-15, 0], duration: 500, ease: 'outExpo' });
    if (desc) animate(desc, { opacity: [0, 1], translateY: [10, 0], duration: 400, delay: 150, ease: 'outExpo' });
  }

  animateIntroSlide(root: Element) {
    const tl = createTimeline({ defaults: { ease: 'outExpo' } });
    const icon = root.querySelector('.rw-intro-icon');
    const year = root.querySelector('.rw-intro-year');
    const label = root.querySelector('.rw-intro-label');
    const divider = root.querySelector('.rw-intro-divider');
    const hint = root.querySelector('.rw-intro-hint');
    if (icon) tl.add(icon, { opacity: [0, 1], scale: [0.5, 1], duration: 600 }, 0);
    if (year) tl.add(year, { opacity: [0, 1], scale: [0.5, 1], duration: 800 }, 200);
    if (label) tl.add(label, { opacity: [0, 1], translateY: [-15, 0], duration: 500 }, 500);
    if (divider) tl.add(divider, { scaleX: [0, 1], opacity: [0, 1], duration: 500 }, 700);
    if (hint) tl.add(hint, { opacity: [0, 0.6], duration: 600 }, 1000);
  }

  animateSummarySlide(root: Element) {
    const title = root.querySelector('.rw-summary-title');
    if (title) animate(title, { opacity: [0, 1], translateY: [-20, 0], duration: 500, ease: 'outExpo' });
    const cards = root.querySelectorAll('.rw-summary-card');
    if (cards.length) {
      animate(cards, { opacity: [0, 1], scale: [0.8, 1], delay: stagger(40, { start: 200 }), duration: 400, ease: 'outBack' });
    }
  }

  animateCountUpSlide(root: Element) {
    root.querySelectorAll('[data-count-target]').forEach((el, i) => {
      const target = parseInt(el.getAttribute('data-count-target') || '0');
      if (target <= 0) return;
      animate(el, { opacity: [0, 1], scale: [2, 1], duration: 800, delay: i * 150 + 300, ease: 'outExpo' });
      const obj = { val: 0 };
      animate(obj, {
        val: target,
        duration: 1500,
        delay: i * 150 + 300,
        ease: 'outExpo',
        onUpdate: () => {
          el.textContent = Math.round(obj.val).toLocaleString();
        },
      });
    });
    const labels = root.querySelectorAll('.rw-community-stat-lbl');
    if (labels.length) animate(labels, { opacity: [0, 1], translateY: [10, 0], delay: stagger(80, { start: 800 }), duration: 400, ease: 'outExpo' });
    const changes = root.querySelectorAll('.rw-change');
    if (changes.length) animate(changes, { opacity: [0, 1], scale: [0.5, 1], delay: stagger(100, { start: 1200 }), duration: 500, ease: 'outBack' });
  }

  animateChartSlide(root: Element) {
    const bars = root.querySelectorAll('.rw-bar-fill');
    if (bars.length) animate(bars, { scaleY: [0, 1.3, 1], delay: stagger(60, { start: 400 }), duration: 800, ease: 'outElastic(1, .5)' });
    const peak = root.querySelector('.rw-chart-peak');
    if (peak) animate(peak, { opacity: [0, 1], scale: [0.3, 1], duration: 800, delay: 1000, ease: 'outElastic(1, .5)' });
  }

  animateClockSlide(root: Element) {
    const tl = createTimeline({ defaults: { ease: 'outExpo' } });
    animateClock(root, tl);
  }

  animateUserListSlide(root: Element) {
    const cards = root.querySelectorAll('.rw-user-card');
    if (cards.length) animate(cards, { opacity: [0, 1], translateX: [-30, 0], delay: stagger(100, { start: 300 }), duration: 600, ease: 'outExpo' });
  }

  animatePreviewSlide(root: Element) {
    const preview = root.querySelector('.rw-post-preview');
    if (preview) animate(preview, { opacity: [0, 1], translateY: [30, 0], scale: [0.95, 1], duration: 800, delay: 300, ease: 'outExpo' });
  }

  animateSpotlightSlide(root: Element) {
    const spotlight = root.querySelector('.rw-spotlight-value');
    if (spotlight) animate(spotlight, { opacity: [0, 1], scale: [0.5, 1], duration: 1000, delay: 300, ease: 'outElastic(1, .6)' });
  }

  animateAvatarGridSlide(root: Element) {
    const avatars = root.querySelectorAll('.rw-members-avatar, .rw-members-more');
    if (avatars.length) animate(avatars, { opacity: [0, 1], scale: [0, 1], delay: stagger(50, { start: 300 }), duration: 500, ease: 'outBack' });
  }

  goBack() {
    m.route.set(app.route('huseyinfiliz-rewind.forum'));
  }

  next() {
    if (this.animating || this.currentSlide >= this.slides.length - 1) return;
    this.direction = 1;
    this.animating = true;
    this.currentSlide++;
    m.redraw();
    setTimeout(() => {
      if (this.isDestroyed) return;
      this.animating = false;
      m.redraw();
    }, SLIDE_TRANSITION_MS);
  }

  prev() {
    if (this.animating || this.currentSlide <= 0) return;
    this.direction = -1;
    this.animating = true;
    this.currentSlide--;
    m.redraw();
    setTimeout(() => {
      if (this.isDestroyed) return;
      this.animating = false;
      m.redraw();
    }, SLIDE_TRANSITION_MS);
  }

  handleClick(e: MouseEvent) {
    const target = e.target as HTMLElement;
    if (target.closest('.rw-close') || target.closest('.rw-summary-slide')) return;
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    if (e.clientX - rect.left < rect.width / 3) this.prev();
    else this.next();
  }

  handleTouchStart(e: TouchEvent) {
    this.touchStartX = e.touches[0].clientX;
  }
  handleTouchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0].clientX - this.touchStartX;
    if (Math.abs(dx) > 50) {
      dx < 0 ? this.next() : this.prev();
    }
  }

  view(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="rw-slideshow rw-slide--loading">
          <LoadingIndicator />
          {this.preparingCommunity && <p>{trans('huseyinfiliz-rewind.forum.community.preparing')}</p>}
        </div>
      );
    }
    if (!this.data || this.slides.length === 0) {
      return (
        <div className="rw-slideshow">
          <div className="rw-slide rw-slide--intro">
            <p style={{ color: '#fff' }}>{trans('huseyinfiliz-rewind.forum.slideshow.not_available') as string}</p>
          </div>
        </div>
      );
    }

    const slide = this.slides[this.currentSlide];
    const typeClass = slide.key ? `rw-slide--community-${slide.key}` : `rw-slide--community-${slide.type}`;
    const dirClass = this.direction > 0 ? 'rw-slide--from-right' : 'rw-slide--from-left';

    return (
      <div
        className="rw-slideshow"
        role="region"
        aria-label="Community Rewind Slideshow"
        aria-roledescription="slideshow"
        onclick={(e: MouseEvent) => this.handleClick(e)}
        ontouchstart={(e: TouchEvent) => this.handleTouchStart(e)}
        ontouchend={(e: TouchEvent) => this.handleTouchEnd(e)}
      >
        <div className="rw-progress" key="progress">
          {this.slides.map((_: any, i: number) => (
            <div className={`rw-progress-seg${i <= this.currentSlide ? ' active' : ''}`} key={i} />
          ))}
        </div>
        <button
          className="rw-close"
          key="close"
          aria-label="Close slideshow"
          onclick={(e: Event) => {
            e.stopPropagation();
            this.goBack();
          }}
        >
          <i className="fas fa-times" />
        </button>
        <div
          className={`rw-slide ${typeClass} ${dirClass}`}
          key={`slide-${this.currentSlide}`}
          role="tabpanel"
          aria-label={`Slide ${this.currentSlide + 1} of ${this.slides.length}`}
        >
          {this.renderSlide(slide)}
        </div>
      </div>
    );
  }

  renderSlide(slide: Slide): Mithril.Children {
    switch (slide.type) {
      case 'intro':
        return this.renderIntro();
      case 'empty':
        return this.renderEmpty();
      case 'year-overview':
        return this.renderYearOverview();
      case 'summary':
        return this.renderSummary();
      case 'metric':
        switch (slide.key) {
          case 'busiest_month':
            return this.renderBusiestMonth();
          case 'peak_hour':
            return this.renderPeakHour();
          case 'top_contributors':
            return this.renderTopContributors();
          case 'star_post':
            return this.renderStarPost();
          case 'most_loved':
            return this.renderMostLoved();
          case 'top_discussion':
            return this.renderTopDiscussion();
          case 'top_tag':
            return this.renderTopTag();
          case 'total_words':
            return this.renderTotalWords();
          case 'new_members':
            return this.renderNewMembers();
          case 'best_answers_leaderboard':
            return this.renderBestAnswersLeaderboard();
          case 'badge_leaderboard':
            return this.renderBadgeLeaderboard();
          default:
            return null;
        }
      default:
        return null;
    }
  }

  renderIntro(): Mithril.Children {
    const year = this.snapshot?.year() || '';
    return (
      <div className="rw-intro-slide">
        <div className="rw-intro-orbs" />
        <div className="rw-intro-icon" style={{ opacity: 0 }}>
          <i className="fas fa-users" />
        </div>
        <div className="rw-intro-year">{year}</div>
        <div className="rw-intro-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.intro_label')}</div>
        <div className="rw-intro-divider" />
        <div className="rw-intro-hint">{trans('huseyinfiliz-rewind.forum.slideshow.tap_to_start')}</div>
      </div>
    );
  }

  renderChangeIndicator(current: number, previous: number | undefined): Mithril.Children {
    if (previous === undefined || previous === 0) return null;
    const pct = Math.round(((current - previous) / previous) * 100);
    if (pct === 0) return null;
    const cls = pct > 0 ? 'rw-change rw-change--up' : 'rw-change rw-change--down';
    return (
      <span className={cls}>
        {pct > 0 ? '+' : ''}
        {pct}%
      </span>
    );
  }

  renderEmpty(): Mithril.Children {
    const year = this.snapshot?.year() || '';
    return (
      <div className="rw-empty-slide">
        <div className="rw-empty-icon">
          <i className="fas fa-ghost" />
        </div>
        <div className="rw-empty-year">{year}</div>
        <div className="rw-empty-text">{trans('huseyinfiliz-rewind.forum.slideshow.no_data')}</div>
        <button className="Button rw-empty-close" onclick={() => this.goBack()}>
          {trans('huseyinfiliz-rewind.forum.slideshow.close')}
        </button>
      </div>
    );
  }

  renderYearOverview(): Mithril.Children {
    const d = this.data!;
    const p = this.prevData;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.year_in_numbers')}</div>
        </div>
        <div className="rw-community-stats">
          {d.total_posts && (
            <div className="rw-community-stat">
              <div className="rw-community-stat-val" data-count-target={d.total_posts.count}>
                0
              </div>
              <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.posts')}</div>
              {this.renderChangeIndicator(d.total_posts.count, p?.total_posts?.count)}
            </div>
          )}
          {d.total_discussions && (
            <div className="rw-community-stat">
              <div className="rw-community-stat-val" data-count-target={d.total_discussions.count}>
                0
              </div>
              <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.discussions')}</div>
              {this.renderChangeIndicator(d.total_discussions.count, p?.total_discussions?.count)}
            </div>
          )}
          {d.new_users && (
            <div className="rw-community-stat">
              <div className="rw-community-stat-val" data-count-target={d.new_users.count}>
                0
              </div>
              <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.new_members')}</div>
              {this.renderChangeIndicator(d.new_users.count, p?.new_users?.count)}
            </div>
          )}
          {d.total_reactions?.count > 0 && (
            <div className="rw-community-stat">
              <div className="rw-community-stat-val" data-count-target={d.total_reactions.count}>
                0
              </div>
              <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.reactions')}</div>
            </div>
          )}
        </div>
      </div>
    );
  }

  renderBusiestMonth(): Mithril.Children {
    const d = this.data!.busiest_month;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.busiest_month')}</div>
          <div className="rw-slide-desc">
            {trans('huseyinfiliz-rewind.forum.community_slideshow.busiest_month_desc', { count: d.peak_count?.toLocaleString() })}
          </div>
        </div>
        <div className="rw-chart-layout">
          <div className="rw-chart">
            {Object.entries(d.months || {}).map(([month, count]) => (
              <div className="rw-bar" key={month}>
                <div
                  className={`rw-bar-fill${parseInt(month) === d.peak_month ? ' rw-bar-fill--peak' : ''}`}
                  style={{ height: d.peak_count ? `${((count as number) / d.peak_count) * 100}%` : '0%' }}
                />
                <span className="rw-bar-label">{MONTH_NAMES[parseInt(month)]}</span>
              </div>
            ))}
          </div>
          <div className="rw-chart-peak" style={{ opacity: 0 }}>
            {MONTH_NAMES_FULL[d.peak_month] || '—'}
          </div>
        </div>
      </div>
    );
  }

  renderPeakHour(): Mithril.Children {
    const d = this.data!.peak_hour;
    const localPeakHour = utcToLocalHour(d.peak_hour);
    const localCounts = shiftHourCounts(d.hour_counts || new Array(24).fill(0));
    const maxCount = Math.max(...localCounts, 1);

    const SIZE = 260,
      CENTER = SIZE / 2,
      INNER_R = 50,
      OUTER_R = 115;
    const BAR_GAP = 1.5,
      BAR_ANGLE = 360 / 24 - BAR_GAP;
    const HOUR_HAND_LEN = INNER_R * 0.55,
      MINUTE_HAND_LEN = INNER_R * 0.78;
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const hourAngle = ((localPeakHour + 0.5) / 24) * 360 - 90;
    const minuteAngle = -90;

    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.peak_hours')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.peak_hours_desc')}</div>
        </div>
        <div className="rw-clock-layout">
          <div className="rw-clock-ring" style={{ opacity: 0 }}>
            <svg viewBox={`0 0 ${SIZE} ${SIZE}`} className="rw-clock-svg">
              <circle cx={CENTER} cy={CENTER} r={INNER_R} className="rw-clock-inner-ring" />
              {localCounts.map((count, h) => {
                const ratio = count > 0 ? Math.max(count / maxCount, 0.06) : 0.06;
                const barLen = INNER_R + (OUTER_R - INNER_R) * ratio;
                const startAngle = (h / 24) * 360 - 90 + BAR_GAP / 2;
                const endAngle = startAngle + BAR_ANGLE;
                const x1i = CENTER + INNER_R * Math.cos(toRad(startAngle));
                const y1i = CENTER + INNER_R * Math.sin(toRad(startAngle));
                const x1o = CENTER + barLen * Math.cos(toRad(startAngle));
                const y1o = CENTER + barLen * Math.sin(toRad(startAngle));
                const x2o = CENTER + barLen * Math.cos(toRad(endAngle));
                const y2o = CENTER + barLen * Math.sin(toRad(endAngle));
                const x2i = CENTER + INNER_R * Math.cos(toRad(endAngle));
                const y2i = CENTER + INNER_R * Math.sin(toRad(endAngle));
                const d = `M ${x1i} ${y1i} L ${x1o} ${y1o} A ${barLen} ${barLen} 0 0 1 ${x2o} ${y2o} L ${x2i} ${y2i} A ${INNER_R} ${INNER_R} 0 0 0 ${x1i} ${y1i} Z`;
                let cls = 'rw-clock-bar';
                if (h === localPeakHour) cls += ' rw-clock-bar--peak';
                else if (count === 0) cls += ' rw-clock-bar--empty';
                return <path key={h} d={d} className={cls} />;
              })}
              <g className="rw-clock-hands">
                <line
                  x1={CENTER}
                  y1={CENTER}
                  x2={CENTER + MINUTE_HAND_LEN * Math.cos(toRad(minuteAngle))}
                  y2={CENTER + MINUTE_HAND_LEN * Math.sin(toRad(minuteAngle))}
                  className="rw-clock-hand rw-clock-hand--minute"
                />
                <line
                  x1={CENTER}
                  y1={CENTER}
                  x2={CENTER + HOUR_HAND_LEN * Math.cos(toRad(hourAngle))}
                  y2={CENTER + HOUR_HAND_LEN * Math.sin(toRad(hourAngle))}
                  className="rw-clock-hand rw-clock-hand--hour"
                />
                <circle cx={CENTER} cy={CENTER} r={3} className="rw-clock-center-dot" />
              </g>
            </svg>
          </div>
          <div className="rw-clock-peak-label" style={{ opacity: 0 }}>{`${localPeakHour.toString().padStart(2, '0')}:00`}</div>
        </div>
      </div>
    );
  }

  renderUserCard(userId: number, username: string, subtitle: string, index: number): Mithril.Children {
    const user = app.store.getById('users', String(userId));
    return (
      <div className="rw-user-card" key={userId} style={{ opacity: 0 }}>
        <div className="rw-user-card-rank">#{index + 1}</div>
        <div className="rw-user-card-avatar">{user ? <Avatar user={user} /> : <i className="fas fa-user-circle" />}</div>
        <div className="rw-user-card-info">
          <span className="rw-user-card-name">{username}</span>
          <span className="rw-user-card-stat">{subtitle}</span>
        </div>
      </div>
    );
  }

  renderTopContributors(): Mithril.Children {
    const users = this.data!.top_contributors.users;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.top_contributors')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.top_contributors_desc')}</div>
        </div>
        <div className="rw-user-list">
          {users.map((u: any, i: number) => this.renderUserCard(u.user_id, u.username, `${u.post_count.toLocaleString()} posts`, i))}
        </div>
      </div>
    );
  }

  renderMostLoved(): Mithril.Children {
    const users = this.data!.most_loved.users;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.most_loved')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.most_loved_desc')}</div>
        </div>
        <div className="rw-user-list">
          {users.map((u: any, i: number) => this.renderUserCard(u.user_id, u.username, `${u.like_count.toLocaleString()} likes`, i))}
        </div>
      </div>
    );
  }

  renderStarPost(): Mithril.Children {
    const d = this.data!.star_post;
    const user = d.user_id ? app.store.getById('users', String(d.user_id)) : null;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.star_post')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.star_post_desc')}</div>
        </div>
        <div className="rw-post-preview" style={{ opacity: 0 }}>
          <div className="rw-post-preview-header">
            <div className="rw-post-preview-left">
              {user && <Avatar user={user} />}
              <span className="rw-post-preview-name">{d.username}</span>
            </div>
            <span className="rw-post-preview-disc">{d.discussion_title}</span>
          </div>
          {(d.content_html || d.excerpt) && <div className="rw-post-preview-body">{d.content_html ? m.trust(d.content_html) : d.excerpt}</div>}
          <div className="rw-quote-meta" style={{ opacity: 0.6 }}>
            <i className="fas fa-heart" /> {d.like_count}
          </div>
        </div>
      </div>
    );
  }

  renderTopDiscussion(): Mithril.Children {
    const d = this.data!.top_discussion;
    const author = d.author_id ? app.store.getById('users', String(d.author_id)) : null;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.hot_discussion')}</div>
          <div className="rw-slide-desc">
            {trans('huseyinfiliz-rewind.forum.community_slideshow.hot_discussion_desc', { count: d.post_count.toLocaleString() })}
          </div>
        </div>
        <div className="rw-post-preview" style={{ opacity: 0 }}>
          <div className="rw-post-preview-header">
            <div className="rw-post-preview-left">
              {author && <Avatar user={author} />}
              <span className="rw-post-preview-name">{d.author_username || ''}</span>
            </div>
          </div>
          <div className="rw-post-preview-body" style={{ fontSize: '1.1em', fontWeight: 700 }}>
            {d.title}
          </div>
          {(d.content_html || d.excerpt) && (
            <div className="rw-post-preview-body" style={{ opacity: 0.7 }}>
              {d.content_html ? m.trust(d.content_html) : d.excerpt}
            </div>
          )}
        </div>
      </div>
    );
  }

  renderTopTag(): Mithril.Children {
    const d = this.data!.top_tag;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.top_tag')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.top_tag_desc', { count: d.discussion_count })}</div>
        </div>
        <div className="rw-spotlight-value" style={{ opacity: 0 }}>
          <span className="rw-spotlight-char rw-spotlight-prefix" style={{ opacity: 1 }}>
            {d.icon ? <i className={d.icon} /> : '#'}
          </span>
          <span style={{ opacity: 1 }}>{d.name}</span>
        </div>
      </div>
    );
  }

  renderTotalWords(): Mithril.Children {
    const d = this.data!.total_words;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.total_words')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.total_words_desc')}</div>
        </div>
        <div className="rw-community-stats">
          <div className="rw-community-stat">
            <div className="rw-community-stat-val" data-count-target={d.total_words}>
              0
            </div>
            <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.total_words_label')}</div>
          </div>
          <div className="rw-community-stat">
            <div className="rw-community-stat-val" data-count-target={Math.round(d.avg_words_per_post)}>
              0
            </div>
            <div className="rw-community-stat-lbl">{trans('huseyinfiliz-rewind.forum.community_slideshow.avg_per_post')}</div>
          </div>
        </div>
      </div>
    );
  }

  renderNewMembers(): Mithril.Children {
    const d = this.data!.new_members_list;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.new_members_slide')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.new_members_desc', { count: d.count })}</div>
        </div>
        <div className="rw-members-avatars">
          {(d.recent || []).map((u: any) => {
            const user = app.store.getById('users', String(u.user_id));
            return (
              <div className="rw-members-avatar" key={u.user_id}>
                {user ? <Avatar user={user} /> : <i className="fas fa-user-circle" />}
              </div>
            );
          })}
          {d.count > (d.recent?.length || 0) && <div className="rw-members-more">+{d.count - (d.recent?.length || 0)}</div>}
        </div>
      </div>
    );
  }

  renderBestAnswersLeaderboard(): Mithril.Children {
    const users = this.data!.best_answers_leaderboard.users;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.best_answers_leaderboard')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.best_answers_leaderboard_desc')}</div>
        </div>
        <div className="rw-user-list">
          {users.map((u: any, i: number) => this.renderUserCard(u.user_id, u.username, `${u.answer_count} solutions`, i))}
        </div>
      </div>
    );
  }

  renderBadgeLeaderboard(): Mithril.Children {
    const users = this.data!.badge_leaderboard.users;
    return (
      <div className="rw-metric-slide rw-metric-slide--community">
        <div className="rw-slide-header">
          <div className="rw-slide-label">{trans('huseyinfiliz-rewind.forum.community_slideshow.badge_leaderboard')}</div>
          <div className="rw-slide-desc">{trans('huseyinfiliz-rewind.forum.community_slideshow.badge_leaderboard_desc')}</div>
        </div>
        <div className="rw-user-list">
          {users.map((u: any, i: number) => this.renderUserCard(u.user_id, u.username, `${u.badge_count} badges`, i))}
        </div>
      </div>
    );
  }

  renderSummary(): Mithril.Children {
    const d = this.data!;
    const items: Array<{ icon: string; value: string; label: string }> = [];

    if (d.total_posts?.count > 0)
      items.push({
        icon: 'fas fa-comment',
        value: d.total_posts.count.toLocaleString(),
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.posts') as string,
      });
    if (d.total_discussions?.count > 0)
      items.push({
        icon: 'fas fa-comments',
        value: d.total_discussions.count.toLocaleString(),
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.discussions') as string,
      });
    if (d.new_users?.count > 0)
      items.push({
        icon: 'fas fa-user-plus',
        value: d.new_users.count.toLocaleString(),
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.new_members') as string,
      });
    if (d.total_words?.total_words > 0)
      items.push({
        icon: 'fas fa-book',
        value: d.total_words.total_words.toLocaleString(),
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.words') as string,
      });
    if (d.most_active_user?.username)
      items.push({
        icon: 'fas fa-trophy',
        value: d.most_active_user.username,
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.top_contributor_label') as string,
      });
    if (d.top_tag?.name)
      items.push({
        icon: 'fas fa-hashtag',
        value: d.top_tag.name,
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.top_tag_label') as string,
      });
    if (d.busiest_month?.peak_count > 0)
      items.push({
        icon: 'fas fa-calendar',
        value: MONTH_NAMES_FULL[d.busiest_month.peak_month] || '',
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.busiest_month_label') as string,
      });
    if (d.peak_hour?.peak_count > 0)
      items.push({
        icon: 'fas fa-clock',
        value: `${utcToLocalHour(d.peak_hour.peak_hour).toString().padStart(2, '0')}:00`,
        label: trans('huseyinfiliz-rewind.forum.community_slideshow.peak_hour_label') as string,
      });

    return (
      <div className="rw-summary-slide" onclick={(e: Event) => e.stopPropagation()}>
        <div className="rw-summary-title">
          {trans('huseyinfiliz-rewind.forum.community_slideshow.summary_title', { year: this.snapshot?.year() })}
        </div>
        <div className="rw-summary-grid">
          {items.map((item, i) => (
            <div className="rw-summary-card" key={i} style={{ opacity: 0 }}>
              <i className={item.icon} />
              <div className="rw-summary-val">{item.value}</div>
              <div className="rw-summary-lbl">{item.label}</div>
            </div>
          ))}
        </div>
        <div className="rw-summary-actions">
          <button className="rw-summary-action-card" onclick={() => this.share()}>
            <i className="fas fa-share-alt" />
            <span>{trans('huseyinfiliz-rewind.forum.slideshow.share')}</span>
          </button>
        </div>
      </div>
    );
  }

  async share() {
    const url = window.location.href;
    if (navigator.share) {
      try {
        await navigator.share({ title: `${this.snapshot?.year()} Community Rewind`, url });
      } catch {}
    } else if (navigator.clipboard) {
      await navigator.clipboard.writeText(url);
      app.alerts.show({ type: 'success' }, extractText(trans('huseyinfiliz-rewind.forum.slideshow.link_copied')));
    }
  }
}
