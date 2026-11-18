import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Avatar from 'flarum/common/components/Avatar';
import extractText from 'flarum/common/utils/extractText';
import { animate, stagger, createTimeline, random } from 'animejs';
import { animateBignum } from '../animators/bignum';
import { animateSpotlight } from '../animators/spotlight';
import { animateQuote } from '../animators/quote';
import { animateHeart } from '../animators/heart';
import { animatePerson } from '../animators/person';
import { animateChart } from '../animators/chart';
import { animateClock } from '../animators/clock';
import { animateWords } from '../animators/words';
import { animateEmojis } from '../animators/emojis';
import type Mithril from 'mithril';

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

const METRIC_ICONS: Record<string, string> = {
  post_count: 'fas fa-pen-fancy',
  discussion_count: 'fas fa-comments',
  active_days: 'fas fa-calendar-check',
  word_count: 'fas fa-book-open',
  most_active_month: 'fas fa-chart-bar',
  top_tag: 'fas fa-hashtag',
  best_post: 'fas fa-trophy',
  likes_given: 'far fa-heart',
  likes_received: 'fas fa-heart',
  best_friend: 'fas fa-user-friends',
  best_friend_mentions: 'fas fa-at',
  badges_earned: 'fas fa-medal',
  best_answers: 'fas fa-check-double',
  top_words: 'fas fa-font',
  top_emojis: 'fas fa-smile',
  night_owl: 'fas fa-clock',
};

const COUNT_METRICS = ['post_count', 'discussion_count', 'active_days', 'word_count', 'likes_given', 'likes_received', 'best_answers'];

const SLIDE_LAYOUTS: Record<string, string> = {
  post_count: 'layout-bignum',
  discussion_count: 'layout-bignum',
  active_days: 'layout-bignum',
  word_count: 'layout-bignum',
  most_active_month: 'layout-chart',
  top_tag: 'layout-spotlight',
  best_post: 'layout-quote',
  likes_given: 'layout-heart',
  likes_received: 'layout-heart',
  best_friend: 'layout-person',
  best_friend_mentions: 'layout-person',
  badges_earned: 'layout-badges',
  best_answers: 'layout-bignum',
  top_words: 'layout-words',
  top_emojis: 'layout-emojis',
  night_owl: 'layout-clock',
};

const LAYOUT_ANIMATORS: Record<string, (root: Element, tl: any, key?: string) => void> = {
  'layout-bignum': (root, tl, key) => animateBignum(root, tl, key!),
  'layout-badges': (root, tl, key) => animateBignum(root, tl, key!),
  'layout-spotlight': (root, tl) => animateSpotlight(root, tl),
  'layout-quote': (root, tl) => animateQuote(root, tl),
  'layout-heart': (root, tl, key) => animateHeart(root, tl, key!),
  'layout-person': (root, tl) => animatePerson(root, tl),
  'layout-chart': (root, tl) => animateChart(root, tl),
  'layout-clock': (root, tl) => animateClock(root, tl),
  'layout-words': (root, tl) => animateWords(root, tl),
  'layout-emojis': (root, tl) => animateEmojis(root, tl),
};

// Mithril VNodes (HTML barındıran çeviriler) ve stringleri güvenle döndürmek için
function trans(key: string, params?: Record<string, any>): any {
  return params ? app.translator.trans(key, params) : app.translator.trans(key);
}

function getTier(value: number, thresholds: number[]): number {
  for (let i = thresholds.length - 1; i >= 0; i--) {
    if (value >= thresholds[i]) return i + 1;
  }
  return 1;
}

function getContextualMessage(key: string, data: Record<string, any>, communityAvg?: Record<string, any>): any | null {
  const count = data.count ?? data.interaction_count ?? data.mention_count ?? 0;
  if (count <= 0) return null;

  const avgMap: Record<string, string> = { post_count: 'posts', discussion_count: 'discussions', word_count: 'words' };
  if (communityAvg && avgMap[key]) {
    const avg = communityAvg[avgMap[key]];
    if (avg && avg > 0) {
      const ratio = count / avg;
      if (ratio >= 2) {
        return trans('huseyinfiliz-rewind.forum.metrics.community_compare.above', { times: Math.round(ratio) });
      } else if (ratio >= 1.2) {
        return trans('huseyinfiliz-rewind.forum.metrics.community_compare.above_average');
      } else if (ratio >= 0.8) {
        return trans('huseyinfiliz-rewind.forum.metrics.community_compare.average');
      } else {
        return trans('huseyinfiliz-rewind.forum.metrics.community_compare.below_average');
      }
    }
  }

  const tierKey = (k: string, tiers: number[]) => {
    const tier = getTier(count, tiers);
    return trans(`huseyinfiliz-rewind.forum.metrics.${k}.messages.tier_${tier}`) || null;
  };
  switch (key) {
    case 'post_count':
      return tierKey('post_count', [1, 11, 51, 201, 501]);
    case 'discussion_count':
      return tierKey('discussion_count', [1, 6, 21]);
    case 'active_days':
      return tierKey('active_days', [1, 6, 21, 51, 101]);
    case 'word_count':
      return tierKey('word_count', [1, 501, 2001, 10001, 50001]);
    case 'likes_given':
      return tierKey('likes_given', [1, 21, 101]);
    case 'likes_received':
      return tierKey('likes_received', [1, 11, 51]);
    case 'best_answers':
      return tierKey('best_answers', [1, 2, 6]);
    case 'best_friend':
      return trans('huseyinfiliz-rewind.forum.metrics.best_friend.message') || null;
    default:
      return null;
  }
}

function utcToLocalHour(utcHour: number): number {
  const now = new Date();
  const offsetMinutes = now.getTimezoneOffset();
  const localHour = (utcHour - offsetMinutes / 60 + 24) % 24;
  return Math.round(localHour) % 24;
}

function shiftHourCounts(hourCounts: number[]): number[] {
  const shifted = new Array(24).fill(0);
  for (let utcH = 0; utcH < 24; utcH++) {
    shifted[utcToLocalHour(utcH)] = hourCounts[utcH] || 0;
  }
  return shifted;
}

function plainDescription(key: string, data: Record<string, any>): any {
  if (key === 'top_words' && !data.words?.length) return '';
  if (key === 'top_emojis' && !data.emojis?.length) return '';
  if (key === 'best_friend' || key === 'best_friend_mentions') return '';

  if (key === 'best_post') {
    if (!data.post_id) return trans(`huseyinfiliz-rewind.forum.metrics.${key}.no_data`);
    const type = data.metric_type === 'likes' ? 'likes' : 'replies';
    return trans(`huseyinfiliz-rewind.forum.metrics.${key}.description_${type}`, data);
  }

  if (key === 'night_owl') {
    return trans(`huseyinfiliz-rewind.forum.metrics.${key}.description`);
  }

  const payload: any = { ...data };
  if (key === 'most_active_month') {
    payload.month = MONTH_NAMES_FULL[data.peak_month] || '';
    payload.count = data.peak_count || 0;
  }
  if (key === 'best_friend') {
    payload.display_name = data.display_name || data.username || '';
    payload.count = data.interaction_count || 0;
  }
  if (key === 'best_friend_mentions') {
    payload.display_name = data.display_name || data.username || '';
    payload.count = data.mention_count || 0;
  }

  return trans(`huseyinfiliz-rewind.forum.metrics.${key}.description`, payload);
}

function getEmojiCdnBase(): string | null {
  return app.forum.attribute<string>('flarum-emoji.cdn') || null;
}

function emojiToCdnUrl(emoji: string): string | null {
  const base = getEmojiCdnBase();
  if (!base) return null;

  const allCodes = [...emoji].map((c) => c.codePointAt(0)!.toString(16));
  const first = allCodes[0];
  if (first === '200d' || first === '20e3' || (parseInt(first, 16) >= 0xfe00 && parseInt(first, 16) <= 0xfe0f)) {
    return null;
  }

  const visible = allCodes.filter((cp) => cp !== 'fe0f' && cp !== '200d' && cp !== '20e3');
  if (visible.length === 0) return null;

  const slug = allCodes.filter((cp) => cp !== 'fe0f').join('-');
  return `${base}72x72/${slug}.png`;
}

const SLIDE_TRANSITION_MS = 450;

export default class SlideshowPage extends Page {
  loading = true;
  snapshot: any = null;
  snapshotData: Record<string, any> | null = null;
  currentSlide = 0;
  direction = 1;
  animating = false;
  slides: Array<{ type: string; key?: string; data?: any }> = [];
  touchStartX = 0;
  touchStartY = 0;
  isDestroyed = false;

  isMetricEmpty(key: string, d: any): boolean {
    if (!d || (typeof d === 'object' && Object.keys(d).length === 0)) return true;
    if (key === 'most_active_month' && !(d.peak_count > 0)) return true;
    if (key === 'top_words' && (!d.words || d.words.length === 0)) return true;
    if (key === 'top_emojis' && (!d.emojis || d.emojis.length === 0)) return true;
    if (key === 'best_post' && !d.post_id) return true;
    if (key === 'best_friend' && !d.user_id) return true;
    if (key === 'best_friend_mentions' && !d.user_id && !d.mentioned_by_user_id) return true;
    if (key === 'best_friend_mentions' && (d.mention_count || 0) === 0 && (d.mentioned_by_count || 0) === 0) return true;
    if (key === 'top_tag' && !d.tag_name) return true;
    if (key === 'night_owl' && !(d.count > 0)) return true;
    if (d.count === 0 && !d.user_id && !d.username && !d.name && !d.tag_name) return true;
    return false;
  }

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle('Rewind');
    this.loadSnapshot();
  }

  async loadSnapshot() {
    const userId = m.route.param('id');
    const year = m.route.param('year') || app.forum.attribute<number>('rewindActiveYear');
    this.loading = true;
    m.redraw();
    try {
      const response = await app.store.find('rw-snaps', {
        filter: { user: userId, year },
        include: 'user',
      } as any);
      const results = Array.isArray(response) ? response : [response];
      this.snapshot = results.length > 0 ? results[0] : null;
      this.snapshotData = this.snapshot?.snapshotData() || null;
      if (this.snapshot) {
        const owner = this.snapshot.user();
        const snapYear = this.snapshot.year();
        const name = owner?.displayName() || `User ${userId}`;
        app.setTitle(`${name}'s ${snapYear} Rewind`);
      }
      if (this.snapshotData) {
        const hiddenRaw = app.forum.attribute<string>('rewindHiddenUserSlides') || '[]';
        let hiddenSlides: string[] = [];
        try {
          hiddenSlides = JSON.parse(hiddenRaw);
        } catch {
          hiddenSlides = [];
        }

        const metricSlides: Array<{ type: string; key?: string; data?: any }> = [];
        for (const key of Object.keys(this.snapshotData)) {
          if (key.startsWith('_')) continue;
          if (hiddenSlides.includes(key)) continue;
          if (this.isMetricEmpty(key, this.snapshotData[key])) continue;
          metricSlides.push({ type: 'metric', key, data: this.snapshotData[key] });
        }

        if (metricSlides.length === 0) {
          this.slides = [{ type: 'empty' }];
        } else {
          this.slides = [{ type: 'intro' }, ...metricSlides, { type: 'summary' }];
        }

        const userIds = [
          this.snapshotData!.best_friend?.user_id,
          this.snapshotData!.best_friend_mentions?.user_id,
          this.snapshotData!.best_friend_mentions?.mentioned_by_user_id,
        ].filter((id) => id && !app.store.getById('users', String(id)));
        const unique = [...new Set(userIds)];
        if (unique.length > 0) {
          Promise.all(unique.map((uid) => app.store.find('users', String(uid)).catch(() => null))).then(() => {
            if (!this.isDestroyed) m.redraw();
          });
        }
      }
    } catch (e) {
      console.error('SlideshowPage: failed to load', e);
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
    this.runSlideAnimations(vnode.dom as Element);
  }

  private lastAnimatedSlide = -1;

  runSlideAnimations(root: Element) {
    if (this.loading) return;
    if (this.lastAnimatedSlide === this.currentSlide) return;
    this.lastAnimatedSlide = this.currentSlide;

    const slide = this.slides[this.currentSlide];
    if (!slide) return;

    if (slide.type === 'intro') {
      this.animateIntro(root);
      return;
    }
    if (slide.type === 'summary') {
      this.animateSummary(root);
      return;
    }

    const layout = SLIDE_LAYOUTS[slide.key!] || 'layout-bignum';
    this.animateMetricSlide(root, slide.key!, layout);
  }

  animateIntro(root: Element) {
    const tl = createTimeline({ defaults: { ease: 'outExpo' } });

    const avatar = root.querySelector('.rw-intro-avatar');
    const year = root.querySelector('.rw-intro-year');
    const label = root.querySelector('.rw-intro-label');
    const divider = root.querySelector('.rw-intro-divider');
    const hint = root.querySelector('.rw-intro-hint');

    if (avatar) tl.add(avatar, { opacity: [0, 1], scale: [0, 1], duration: 800, ease: 'outElastic(1, .5)' }, 0);
    if (year) tl.add(year, { opacity: [0, 1], scale: [0.5, 1], duration: 800 }, 300);
    if (label) tl.add(label, { opacity: [0, 1], translateY: ['-15px', '0px'], duration: 500 }, 600);
    if (divider) tl.add(divider, { scaleX: [0, 1], opacity: [0, 1], duration: 500 }, 800);
    if (hint) tl.add(hint, { opacity: [0, 0.6], duration: 600 }, 1200);

    setTimeout(() => {
      // Zombi DOM manipülasyonunu engellemek için güvenlik kontrolü
      if (this.isDestroyed || !document.body.contains(root)) return;

      const burstContainer = document.createElement('div');
      burstContainer.className = 'rw-intro-burst';
      root.appendChild(burstContainer);

      const colors = ['#667eea', '#764ba2', '#f093fb', '#00c6ff', '#38ef7d', '#ffd200'];
      const particles: HTMLElement[] = [];
      for (let i = 0; i < 120; i++) {
        const particle = document.createElement('div');
        particle.className = 'rw-intro-particle';
        particle.style.background = colors[Math.floor(Math.random() * colors.length)];
        burstContainer.appendChild(particle);
        particles.push(particle);
      }

      animate(particles, {
        translateX: () => (Math.random() - 0.5) * 600,
        translateY: () => (Math.random() - 0.5) * 600 - 100,
        scale: [1, 0],
        opacity: [1, 0],
        duration: () => 800 + Math.random() * 600,
        ease: 'easeOutExpo',
        complete: () => {
          if (!this.isDestroyed && root.contains(burstContainer)) root.removeChild(burstContainer);
        },
      });
    }, 700);
  }

  animateSummary(root: Element) {
    const cards = root.querySelectorAll('.rw-summary-card');
    const title = root.querySelector('.rw-summary-title');
    if (title) animate(title, { opacity: [0, 1], translateY: ['-20px', '0px'], duration: 500, ease: 'outExpo' });
    if (cards.length > 0) {
      animate(cards, {
        opacity: [0, 1],
        scale: [0.8, 1],
        delay: stagger(50, { start: 200 }),
        duration: 400,
        ease: 'outBack',
      });
    }
  }

  animateMetricSlide(root: Element, key: string, layout: string) {
    const tl = createTimeline({ defaults: { ease: 'outExpo' } });

    const ambientShapes = root.querySelectorAll('.rw-ambient-shape');
    if (ambientShapes.length > 0) {
      animate(ambientShapes, {
        opacity: [0, 1],
        scale: [0.5, 1],
        delay: stagger(150),
        duration: 1200,
        ease: 'outExpo',
      });

      ambientShapes.forEach((shape) => {
        const dx = random(-60, 60);
        const dy = random(-60, 60);
        const s = random(1, 1.2);
        animate(shape, {
          translateX: [0, dx, 0],
          translateY: [0, dy, 0],
          scale: [1, s, 1],
          duration: random(10000, 18000),
          loop: true,
          ease: 'easeInOutSine',
        });
      });
    }

    const label = root.querySelector('.rw-slide-label');
    const desc = root.querySelector('.rw-slide-desc');
    const msg = root.querySelector('.rw-slide-msg');

    const animator = LAYOUT_ANIMATORS[layout];
    if (animator) animator(root, tl, key);

    if (label) tl.add(label, { opacity: [0, 1], translateY: ['10px', '0px'], duration: 400 }, 350);
    if (desc) tl.add(desc, { opacity: [0, 1], translateY: ['10px', '0px'], duration: 400 }, 450);
    if (msg) tl.add(msg, { opacity: [0, 1], duration: 400 }, 600);
  }

  goBack() {
    if (this.snapshot) {
      const user = this.snapshot.user();
      if (user) {
        m.route.set(app.route('huseyinfiliz-rewind.profile', { username: user.slug() }));
        return;
      }
    }
    window.history.back();
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
    if (target.closest('.rw-controls') || target.closest('.rw-close') || target.closest('.rw-summary-slide')) return;
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    if (e.clientX - rect.left < rect.width / 3) this.prev();
    else this.next();
  }

  handleTouchStart(e: TouchEvent) {
    this.touchStartX = e.touches[0].clientX;
    this.touchStartY = e.touches[0].clientY;
  }

  handleTouchEnd(e: TouchEvent) {
    const dx = e.changedTouches[0].clientX - this.touchStartX;
    const dy = e.changedTouches[0].clientY - this.touchStartY;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 50) {
      dx < 0 ? this.next() : this.prev();
    }
  }

  async togglePublic() {
    if (!this.snapshot) return;
    await this.snapshot.save({ isPublic: !this.snapshot.isPublic() });
    if (!this.isDestroyed) m.redraw();
  }

  renderAmbientShapes(): Mithril.Children {
    const shapes = [];
    for (let i = 0; i < 5; i++) {
      shapes.push(<div className={`rw-ambient-shape rw-ambient-shape--${i}`} key={i} />);
    }
    return <div className="rw-ambient-container">{shapes}</div>;
  }

  view(): Mithril.Children {
    if (this.loading) {
      return (
        <div className="rw-slideshow rw-slide--loading">
          <LoadingIndicator />
        </div>
      );
    }
    if (!this.snapshotData || this.slides.length === 0) {
      return (
        <div className="rw-slideshow">
          <div className="rw-slide rw-slide--intro">
            <p style={{ color: '#fff' }}>{trans('huseyinfiliz-rewind.forum.page.not_available')}</p>
          </div>
        </div>
      );
    }

    const slide = this.slides[this.currentSlide];
    const slideTypeClass = slide.type === 'metric' ? `rw-slide--${slide.key}` : `rw-slide--${slide.type}`;
    const dirClass = this.direction > 0 ? 'rw-slide--from-right' : 'rw-slide--from-left';

    return (
      <div
        className="rw-slideshow"
        role="region"
        aria-label="Rewind Slideshow"
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
          className={`rw-slide ${slideTypeClass} ${dirClass}`}
          key={`slide-${this.currentSlide}`}
          role="tabpanel"
          aria-label={`Slide ${this.currentSlide + 1} of ${this.slides.length}`}
        >
          {slide.type === 'intro'
            ? this.renderIntro()
            : slide.type === 'summary'
              ? this.renderSummary()
              : slide.type === 'empty'
                ? this.renderEmpty()
                : this.renderMetricSlide(slide.key!, slide.data)}
        </div>
      </div>
    );
  }

  renderIntro(): Mithril.Children {
    const year = this.snapshot?.year() || '';
    const user = this.snapshot?.user();
    return (
      <div className="rw-intro-slide">
        <div className="rw-intro-orbs" />
        {user && (
          <div className="rw-intro-avatar" style={{ opacity: 0 }}>
            <Avatar user={user} />
          </div>
        )}
        <div className="rw-intro-year">{year}</div>
        <div className="rw-intro-label">REWIND</div>
        <div className="rw-intro-divider" />
        <div className="rw-intro-hint">{trans('huseyinfiliz-rewind.forum.slideshow.tap_to_start')}</div>
      </div>
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

  splitText(text: string, className: string): Mithril.Children {
    return text.split('').map((char, i) => (
      <span className={className} key={i} style={{ opacity: 0 }}>
        {char === ' ' ? '\u00A0' : char}
      </span>
    ));
  }

  renderMetricSlide(key: string, data: Record<string, any>): Mithril.Children {
    const layout = SLIDE_LAYOUTS[key] || 'layout-bignum';
    const description = plainDescription(key, data);
    const message = getContextualMessage(key, data, this.snapshotData?._community_avg);
    const icon = METRIC_ICONS[key] || 'fas fa-chart-line';
    const title = trans(`huseyinfiliz-rewind.forum.metrics.${key}.title`);

    if (layout === 'layout-words') return this.renderWordsSlide(data);
    if (layout === 'layout-emojis') return this.renderEmojisSlide(data);

    let mainContent: Mithril.Children = null;

    if (layout === 'layout-bignum') {
      mainContent = this.renderBignumContent(key, data);
    } else if (layout === 'layout-spotlight') {
      mainContent = this.renderSpotlightContent(key, data);
    } else if (layout === 'layout-quote') {
      mainContent = this.renderQuoteContent(data);
    } else if (layout === 'layout-clock') {
      mainContent = this.renderClockContent(data);
    } else if (layout === 'layout-heart') {
      mainContent = this.renderHeartContent(key, data);
    } else if (layout === 'layout-person') {
      mainContent = this.renderPersonContent(key, data);
    } else if (layout === 'layout-chart') {
      mainContent = this.renderChartContent(data);
    } else if (layout === 'layout-badges') {
      mainContent = this.renderBadgesContent(data);
    }

    return (
      <div className={`rw-metric-slide rw-metric-slide--${layout}`}>
        {this.renderAmbientShapes()}
        {key === 'likes_received' && <div className="rw-heart-stream" />}
        {layout !== 'layout-clock' && (
          <div className="rw-bg-watermark">
            <i className={icon} />
          </div>
        )}
        <div className="rw-slide-header">
          <div className="rw-slide-label">{title}</div>
          <div className="rw-slide-desc">{description}</div>
        </div>
        {mainContent}
        {message && <div className="rw-slide-msg">{message}</div>}
      </div>
    );
  }

  renderBignumContent(key: string, data: Record<string, any>): Mithril.Children {
    const isCount = COUNT_METRICS.includes(key) || key === 'badges_earned';
    if (isCount) {
      return (
        <div className={`rw-bignum-wrap rw-bignum-wrap--${key}`}>
          <div className="rw-bignum" data-target={data.count} data-metric={key}>
            0
          </div>
        </div>
      );
    }
    const text = data.count?.toLocaleString() || '—';
    return <div className="rw-bignum rw-bignum--text">{this.splitText(text, 'rw-bignum-char')}</div>;
  }

  renderBadgesContent(data: Record<string, any>): Mithril.Children {
    const badges = data.badges || [];
    const remaining = (data.count || 0) - badges.length;
    return (
      <div className="rw-badges-layout">
        <div className="rw-bignum" data-target={data.count} data-metric="badges_earned" style={{ opacity: 0 }}>
          0
        </div>
        {badges.length > 0 && (
          <div className="rw-badges-showcase">
            {badges.map((b: any, i: number) => (
              <div className="rw-badge-item" key={i} style={{ opacity: 0 }}>
                <i className={b.icon || 'fas fa-medal'} />
                <span>{b.name}</span>
              </div>
            ))}
            {remaining > 0 && (
              <div className="rw-badge-item rw-badge-item--more" style={{ opacity: 0 }}>
                <i className="fas fa-medal" />
                <span>+{remaining} more</span>
              </div>
            )}
          </div>
        )}
      </div>
    );
  }

  renderSpotlightContent(key: string, data: Record<string, any>): Mithril.Children {
    const tagName = data.tag_name || '—';
    const tagIcon = data.tag_icon || null;
    const prefix = tagIcon ? <i className={tagIcon} /> : '#';

    return (
      <div className="rw-spotlight-value">
        <span className="rw-spotlight-char rw-spotlight-prefix" style={{ opacity: 0 }}>
          {prefix}
        </span>
        {this.splitText(tagName, 'rw-spotlight-char')}
      </div>
    );
  }

  renderQuoteContent(data: Record<string, any>): Mithril.Children {
    const title = data.discussion_title || '—';
    const contentHtml = data.content_html || '';
    const excerpt = data.excerpt || '';
    const bodyContent = contentHtml ? m.trust(contentHtml) : excerpt;
    const user = this.snapshot?.user();

    return (
      <div className="rw-quote-wrap">
        {contentHtml || excerpt ? (
          <div className="rw-post-preview">
            <div className="rw-post-preview-header">
              <div className="rw-post-preview-left">
                {user && <Avatar user={user} />}
                <span className="rw-post-preview-name">{user?.displayName() || ''}</span>
              </div>
              <span className="rw-post-preview-disc">{title}</span>
            </div>
            <div className="rw-post-preview-body">{bodyContent}</div>
            <div className="rw-quote-meta">
              {data.metric_type === 'likes' ? (
                <>
                  <i className="fas fa-heart" /> {data.count}
                </>
              ) : (
                <>
                  <i className="fas fa-reply" /> {data.count}
                </>
              )}
            </div>
          </div>
        ) : (
          <>
            <div className="rw-quote-mark">&ldquo;</div>
            <div className="rw-quote-text">{this.splitText(title, 'rw-quote-char')}</div>
            <div className="rw-quote-meta">
              {data.metric_type === 'likes' ? (
                <>
                  <i className="fas fa-heart" /> {data.count}
                </>
              ) : (
                <>
                  <i className="fas fa-reply" /> {data.count}
                </>
              )}
            </div>
          </>
        )}
      </div>
    );
  }

  renderClockContent(data: Record<string, any>): Mithril.Children {
    const isOwl = data.is_night_owl;
    const localPeakHour = utcToLocalHour(data.peak_hour);

    const rawCounts: number[] = data.hour_counts || new Array(24).fill(0);
    const localCounts = shiftHourCounts(rawCounts);
    const maxCount = Math.max(...localCounts, 1);

    const SIZE = 260;
    const CENTER = SIZE / 2;
    const INNER_R = 50;
    const OUTER_R = 115;
    const MIN_BAR_RATIO = 0.06;
    const BAR_GAP = 1.5;
    const BAR_ANGLE = 360 / 24 - BAR_GAP;
    const HOUR_HAND_LEN = INNER_R * 0.55;
    const MINUTE_HAND_LEN = INNER_R * 0.78;

    const toRad = (deg: number) => (deg * Math.PI) / 180;

    const hourAngleFinal = ((localPeakHour + 0.5) / 24) * 360 - 90;
    const minuteAngleFinal = -90;

    const hourX = CENTER + HOUR_HAND_LEN * Math.cos(toRad(hourAngleFinal));
    const hourY = CENTER + HOUR_HAND_LEN * Math.sin(toRad(hourAngleFinal));
    const minuteX = CENTER + MINUTE_HAND_LEN * Math.cos(toRad(minuteAngleFinal));
    const minuteY = CENTER + MINUTE_HAND_LEN * Math.sin(toRad(minuteAngleFinal));

    const barPaths = localCounts.map((count, h) => {
      const ratio = count > 0 ? Math.max(count / maxCount, MIN_BAR_RATIO) : MIN_BAR_RATIO;
      const barLen = INNER_R + (OUTER_R - INNER_R) * ratio;
      const startAngle = (h / 24) * 360 - 90 + BAR_GAP / 2;
      const endAngle = startAngle + BAR_ANGLE;
      const isPeak = h === localPeakHour;
      const isNight = h >= 22 || h <= 5;
      const hasActivity = count > 0;

      const x1i = CENTER + INNER_R * Math.cos(toRad(startAngle));
      const y1i = CENTER + INNER_R * Math.sin(toRad(startAngle));
      const x1o = CENTER + barLen * Math.cos(toRad(startAngle));
      const y1o = CENTER + barLen * Math.sin(toRad(startAngle));
      const x2o = CENTER + barLen * Math.cos(toRad(endAngle));
      const y2o = CENTER + barLen * Math.sin(toRad(endAngle));
      const x2i = CENTER + INNER_R * Math.cos(toRad(endAngle));
      const y2i = CENTER + INNER_R * Math.sin(toRad(endAngle));

      const largeArc = BAR_ANGLE > 180 ? 1 : 0;
      const d = [
        `M ${x1i} ${y1i}`,
        `L ${x1o} ${y1o}`,
        `A ${barLen} ${barLen} 0 ${largeArc} 1 ${x2o} ${y2o}`,
        `L ${x2i} ${y2i}`,
        `A ${INNER_R} ${INNER_R} 0 ${largeArc} 0 ${x1i} ${y1i}`,
        'Z',
      ].join(' ');

      let cls = 'rw-clock-bar';
      if (isPeak) cls += ' rw-clock-bar--peak';
      else if (!hasActivity) cls += ' rw-clock-bar--empty';
      else if (isNight) cls += ' rw-clock-bar--night';

      return <path key={h} d={d} className={cls} style={{ opacity: 0 }} data-hour={h} />;
    });

    const formatHour = (h: number) => `${h.toString().padStart(2, '0')}:00`;

    return (
      <div className="rw-clock-layout">
        <div className="rw-clock-ring" style={{ opacity: 0 }}>
          <svg viewBox={`0 0 ${SIZE} ${SIZE}`} className="rw-clock-svg">
            <circle cx={CENTER} cy={CENTER} r={INNER_R} className="rw-clock-inner-ring" />
            {barPaths}

            <g className="rw-clock-hands" data-hour-angle={hourAngleFinal} data-minute-angle={minuteAngleFinal}>
              <line x1={CENTER} y1={CENTER} x2={minuteX} y2={minuteY} className="rw-clock-hand rw-clock-hand--minute" />
              <line x1={CENTER} y1={CENTER} x2={hourX} y2={hourY} className="rw-clock-hand rw-clock-hand--hour" />
              <circle cx={CENTER} cy={CENTER} r={3} className="rw-clock-center-dot" />
            </g>
          </svg>

          {isOwl && (
            <div className="rw-clock-stars">
              {Array.from({ length: 8 }).map((_, i) => (
                <div key={i} className="rw-clock-star" style={{ opacity: 0 }}>
                  ★
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="rw-clock-peak-label" style={{ opacity: 0 }}>
          {formatHour(localPeakHour)}
        </div>
        <div className="rw-clock-type-label" style={{ opacity: 0 }}>
          {isOwl ? 'Night Owl' : 'Early Bird'}
        </div>
      </div>
    );
  }

  renderHeartContent(key: string, data: Record<string, any>): Mithril.Children {
    if (key === 'likes_received') {
      return (
        <div className="rw-heart-layout" data-metric="likes_received">
          <div className="rw-bignum" data-target={data.count} style={{ opacity: 0 }}>
            0
          </div>
        </div>
      );
    }
    return (
      <div className="rw-heart-layout" data-metric="likes_given">
        <div className="rw-heart-burst">
          {Array.from({ length: 12 }).map((_, i) => (
            <span key={i} style={{ opacity: 0 }}>
              <i className="far fa-heart" />
            </span>
          ))}
        </div>
        <div className="rw-bignum" data-target={data.count} style={{ opacity: 0 }}>
          0
        </div>
      </div>
    );
  }

  renderPersonContent(key: string, data: Record<string, any>): Mithril.Children {
    const name = data.display_name || data.username || '—';
    const friend = data.user_id ? app.store.getById('users', String(data.user_id)) : null;
    const owner = this.snapshot?.user() || null;

    if (key === 'best_friend') {
      const stats = [
        { icon: 'fas fa-heart', label: trans('huseyinfiliz-rewind.forum.metrics.best_friend.stat_likes_received'), value: data.likes_received || 0 },
        { icon: 'far fa-heart', label: trans('huseyinfiliz-rewind.forum.metrics.best_friend.stat_likes_given'), value: data.likes_given || 0 },
      ];
      if (data.mentions_from > 0 || data.mentions_to > 0) {
        stats.push(
          { icon: 'fas fa-at', label: trans('huseyinfiliz-rewind.forum.metrics.best_friend.stat_mentions_from'), value: data.mentions_from || 0 },
          { icon: 'far fa-at', label: trans('huseyinfiliz-rewind.forum.metrics.best_friend.stat_mentions_to'), value: data.mentions_to || 0 }
        );
      }

      return (
        <div className="rw-person-layout rw-person-layout--friend">
          <div className="rw-person-duo">
            <div className="rw-person-duo-avatar rw-person-duo-avatar--owner">
              {owner ? <Avatar user={owner} /> : <i className="fas fa-user-circle" />}
            </div>
            <div className="rw-person-duo-avatar rw-person-duo-avatar--friend">
              {friend ? <Avatar user={friend} /> : <i className="fas fa-user-circle" />}
            </div>
          </div>
          <div className="rw-person-name" style={{ opacity: 1 }}>
            {this.splitText(name, 'rw-person-char')}
          </div>
          <div className="rw-person-stats">
            {stats.map((s, i) => (
              <div className="rw-person-stat" key={i} style={{ opacity: 0 }}>
                <i className={s.icon} />
                <span className="rw-person-stat-val">{s.value}</span>
                <span className="rw-person-stat-lbl">{s.label as string}</span>
              </div>
            ))}
          </div>
        </div>
      );
    }

    if (key === 'best_friend_mentions') {
      const mentionedByUser = data.mentioned_by_user_id ? app.store.getById('users', String(data.mentioned_by_user_id)) : null;

      return (
        <div className="rw-person-layout rw-person-layout--mentions">
          <div className="rw-mention-cards">
            {data.user_id && (
              <div className="rw-mention-card" style={{ opacity: 0 }}>
                <div className="rw-mention-card-avatar">{friend ? <Avatar user={friend} /> : <i className="fas fa-user-circle" />}</div>
                <div className="rw-mention-card-info">
                  <span className="rw-mention-card-name">{data.display_name || data.username}</span>
                  <span className="rw-mention-card-stat">
                    <i className="fas fa-at" />{' '}
                    {trans('huseyinfiliz-rewind.forum.metrics.best_friend_mentions.you_mentioned', { count: data.mention_count || 0 })}
                  </span>
                </div>
              </div>
            )}
            {data.mentioned_by_user_id && (
              <div className="rw-mention-card" style={{ opacity: 0 }}>
                <div className="rw-mention-card-avatar">
                  {mentionedByUser ? <Avatar user={mentionedByUser} /> : <i className="fas fa-user-circle" />}
                </div>
                <div className="rw-mention-card-info">
                  <span className="rw-mention-card-name">{data.mentioned_by_username}</span>
                  <span className="rw-mention-card-stat">
                    <i className="fas fa-at" />{' '}
                    {trans('huseyinfiliz-rewind.forum.metrics.best_friend_mentions.mentioned_you', { count: data.mentioned_by_count || 0 })}
                  </span>
                </div>
              </div>
            )}
          </div>
        </div>
      );
    }

    return (
      <div className="rw-person-layout">
        <div className="rw-person-avatar" style={{ opacity: 0 }}>
          {friend ? <Avatar user={friend} /> : <i className="fas fa-user-circle" />}
        </div>
        <div className="rw-person-name" style={{ opacity: 1 }}>
          {this.splitText(name, 'rw-person-char')}
        </div>
      </div>
    );
  }

  renderChartContent(data: Record<string, any>): Mithril.Children {
    return (
      <div className="rw-chart-layout">
        <div className="rw-chart">
          {Object.entries(data.months || {}).map(([month, count]) => (
            <div className="rw-bar" key={month}>
              <div
                className={`rw-bar-fill${parseInt(month) === data.peak_month ? ' rw-bar-fill--peak' : ''}`}
                style={{
                  height: data.peak_count ? `${((count as number) / data.peak_count) * 100}%` : '0%',
                }}
              />
              <span className="rw-bar-label">{MONTH_NAMES[parseInt(month)]}</span>
            </div>
          ))}
        </div>
        <div className="rw-chart-peak" style={{ opacity: 0 }}>
          {MONTH_NAMES_FULL[data.peak_month] || '—'}
        </div>
      </div>
    );
  }

  renderWordsSlide(data: Record<string, any>): Mithril.Children {
    const words = data.words || [];

    return (
      <div className="rw-words-slide">
        <div className="rw-slide-header">
          <div className="rw-slide-label rw-slide-label--lg">{trans('huseyinfiliz-rewind.forum.metrics.top_words.title')}</div>
          <div className="rw-slide-desc">{plainDescription('top_words', data)}</div>
        </div>
        <div className="rw-words-cloud">
          {words.map((w: any, i: number) => (
            <span
              className="rw-word-item"
              key={i}
              data-word={w.word}
              style={{
                fontSize: `${[2.2, 1.8, 1.5, 1.3, 1.1, 0.95, 0.85, 0.75][i] || 0.75}em`,
                opacity: 0,
              }}
            >
              {w.word}
              <span className="rw-word-count">×{w.count}</span>
            </span>
          ))}
        </div>
      </div>
    );
  }

  renderEmojiChar(emoji: string): Mithril.Children {
    const url = emojiToCdnUrl(emoji);
    if (url) {
      return (
        <img
          className="rw-emoji-char rw-emoji-char--img"
          src={url}
          alt={emoji}
          draggable={false}
          onerror={(ev: Event) => {
            const img = ev.target as HTMLImageElement;
            const span = document.createElement('span');
            span.className = 'rw-emoji-char';
            span.textContent = emoji;
            img.replaceWith(span);
          }}
        />
      );
    }
    return <span className="rw-emoji-char">{emoji}</span>;
  }

  renderEmojisSlide(data: Record<string, any>): Mithril.Children {
    const emojis = data.emojis || [];
    return (
      <div className="rw-emojis-slide">
        <div className="rw-slide-header">
          <div className="rw-slide-label rw-slide-label--lg">{trans('huseyinfiliz-rewind.forum.metrics.top_emojis.title')}</div>
          <div className="rw-slide-desc">{plainDescription('top_emojis', data)}</div>
        </div>
        <div className="rw-emoji-grid">
          {emojis.map((e: any, i: number) => (
            <div className="rw-emoji-item" key={i} style={{ opacity: 0 }}>
              {this.renderEmojiChar(e.emoji)}
              <span className="rw-emoji-count" style={{ opacity: 0 }}>
                ×{e.count}
              </span>
            </div>
          ))}
        </div>
      </div>
    );
  }

  renderSummary(): Mithril.Children {
    const data = this.snapshotData!;
    const keys = Object.keys(data).filter((k) => {
      if (k.startsWith('_')) return false;
      return !this.isMetricEmpty(k, data[k]);
    });
    const isOwner = app.session.user && this.snapshot?.user()?.id() === app.session.user.id();

    return (
      <div className="rw-summary-slide" onclick={(e: Event) => e.stopPropagation()}>
        <div className="rw-summary-title">{trans('huseyinfiliz-rewind.forum.slideshow.summary_title', { year: this.snapshot?.year() })}</div>
        <div className="rw-summary-grid">
          {keys.map((key) => (
            <div className={`rw-summary-card rw-summary-card--${key}`} key={key} style={{ opacity: 0 }}>
              <i className={METRIC_ICONS[key] || 'fas fa-chart-line'} />
              <div className="rw-summary-val">{this.renderSummaryValue(key, data[key])}</div>
              <div className="rw-summary-lbl">{trans(`huseyinfiliz-rewind.forum.metrics.${key}.title`)}</div>
            </div>
          ))}
        </div>
        <div className="rw-summary-actions">
          <button className="rw-summary-action-card" onclick={() => this.share()}>
            <i className="fas fa-share-alt" />
            <span>{trans('huseyinfiliz-rewind.forum.slideshow.share')}</span>
          </button>
          {isOwner && (
            <button className="rw-summary-action-card" onclick={() => this.togglePublic()}>
              <span className={`rw-visibility-pill ${this.snapshot.isPublic() ? 'rw-visibility-pill--public' : 'rw-visibility-pill--private'}`}>
                <i className={this.snapshot.isPublic() ? 'fas fa-eye' : 'fas fa-eye-slash'} />
                {this.snapshot.isPublic()
                  ? trans('huseyinfiliz-rewind.forum.slideshow.visibility_status_public')
                  : trans('huseyinfiliz-rewind.forum.slideshow.visibility_status_private')}
              </span>
              <span className="rw-visibility-hint">
                {this.snapshot.isPublic()
                  ? trans('huseyinfiliz-rewind.forum.slideshow.visibility_tap_hide')
                  : trans('huseyinfiliz-rewind.forum.slideshow.visibility_tap_show')}
              </span>
            </button>
          )}
        </div>
      </div>
    );
  }

  async share() {
    const url = window.location.href;
    if (navigator.share) {
      try {
        await navigator.share({ title: `${this.snapshot?.year()} Rewind`, url });
      } catch {}
    } else if (navigator.clipboard) {
      await navigator.clipboard.writeText(url);
      app.alerts.show({ type: 'success' }, extractText(trans('huseyinfiliz-rewind.forum.slideshow.link_copied')));
    }
  }

  renderSummaryValue(key: string, data: Record<string, any>): Mithril.Children {
    if (key === 'top_emojis') {
      const emoji = data.emojis?.[0]?.emoji;
      if (!emoji) return '—';
      const url = emojiToCdnUrl(emoji);
      if (url) return <img className="rw-summary-emoji" src={url} alt={emoji} draggable={false} />;
      return emoji;
    }
    return this.getSummaryValue(key, data);
  }

  getSummaryValue(key: string, data: Record<string, any>): string {
    if (COUNT_METRICS.includes(key) || key === 'badges_earned') {
      const n = data.count || 0;
      return n >= 1000 ? `${(n / 1000).toFixed(1)}k` : n.toString();
    }
    if (key === 'top_tag') return data.tag_name || '—';
    if (key === 'most_active_month') return MONTH_NAMES[data.peak_month] || '—';
    if (key === 'best_friend') return data.username || '—';
    if (key === 'best_friend_mentions') {
      const total = (data.mention_count || 0) + (data.mentioned_by_count || 0);
      return total > 0 ? total.toString() : '—';
    }
    if (key === 'best_post') return data.count ? `${data.count}` : '—';
    if (key === 'top_words') return data.words?.[0]?.word || '—';
    if (key === 'top_emojis') return data.emojis?.[0]?.emoji || '—';
    if (key === 'night_owl') {
      if (data.peak_hour === null) return '—';
      const h = utcToLocalHour(data.peak_hour);
      return `${h.toString().padStart(2, '0')}:00`;
    }
    return '—';
  }
}
