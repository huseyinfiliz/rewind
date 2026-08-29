import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import { getYearRenderMode } from '../../common/utils/getYearRenderMode';
import type Mithril from 'mithril';

export default class ForumRewindPage extends Page {
  loading = true;
  loadingContent = false;
  generating = false;
  communitySnapshot: any = null;
  userHasSnapshot = false;
  selectedYear = 0;
  availableYears: number[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(app.translator.trans('huseyinfiliz-rewind.forum.page.title') as string);

    this.selectedYear = app.forum.attribute<number>('rewindActiveYear');

    const canView = app.forum.attribute<boolean>('canViewRewind');
    const canModerate = app.forum.attribute<boolean>('canModerateRewind');
    const enabled = app.forum.attribute<boolean>('rewindEnabled');

    if ((enabled && canView) || canModerate) {
      this.loadAvailableYears().then(() => this.loadData(true));
    } else {
      this.loading = false;
    }
  }

  async loadAvailableYears() {
    try {
      const response = await app.store.find('rw-community', {
        sort: '-year',
        page: { limit: 20 },
      } as any);
      const results = Array.isArray(response) ? response : [response];
      const years = results
        .filter((s: any) => s && s.year)
        .map((s: any) => s.year())
        .sort((a: number, b: number) => b - a);

      this.availableYears = [...new Set(years)] as number[];

      if (!this.availableYears.includes(this.selectedYear)) {
        this.availableYears.unshift(this.selectedYear);
      }
    } catch {
      this.availableYears = [this.selectedYear];
    }
  }

  async loadData(initial = false) {
    if (initial) {
      this.loading = true;
    } else {
      this.loadingContent = true;
    }
    m.redraw();

    const year = this.selectedYear;

    try {
      const response = await app.store.find('rw-community', {
        filter: { year },
      } as any);
      const results = Array.isArray(response) ? response : [response];
      this.communitySnapshot = results.length > 0 ? results[0] : null;
    } catch {
      this.communitySnapshot = null;
    }

    const user = app.session.user;
    if (user) {
      try {
        const userResponse = await app.store.find('rw-snaps', {
          filter: { user: user.id(), year },
        } as any);
        const userResults = Array.isArray(userResponse) ? userResponse : [userResponse];
        this.userHasSnapshot = userResults.length > 0 && userResults[0] !== null;
      } catch {
        this.userHasSnapshot = false;
      }
    }

    this.loading = false;
    this.loadingContent = false;
    m.redraw();
  }

  switchYear(year: number) {
    if (year === this.selectedYear) return;
    this.selectedYear = year;
    this.loadData();
  }

  confirmRegenerate() {
    if (confirm(app.translator.trans('huseyinfiliz-rewind.forum.community.regenerate_confirm') as string)) {
      this.generateCommunity();
    }
  }

  async generateCommunity() {
    this.generating = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-community/generate',
      });
      await this.loadData();
    } catch (e: any) {
      const msg = e?.responseJSON?.errors?.[0]?.detail || e?.message || 'Failed to generate community rewind';
      app.alerts.show({ type: 'error' }, msg);
    }

    this.generating = false;
    m.redraw();
  }

  view(): Mithril.Children {
    const year = this.selectedYear;
    const user = app.session.user;

    const canView = app.forum.attribute<boolean>('canViewRewind');
    const canModerate = app.forum.attribute<boolean>('canModerateRewind');
    const enabled = app.forum.attribute<boolean>('rewindEnabled');

    if (!(enabled && canView) && !canModerate) {
      return (
        <div className="RewindPage">
          <div className="container">
            <div className="RewindPage-emptyState">
              <div className="RewindPage-emptyStateIcon">
                <i className="fas fa-lock"></i>
              </div>
              <h3>{app.translator.trans('huseyinfiliz-rewind.forum.page.not_available')}</h3>
            </div>
          </div>
        </div>
      );
    }

    return (
      <PageStructure className="RewindPage" hero={() => this.renderHero(enabled, canModerate)} sidebar={() => <IndexSidebar />}>
        <div className="RewindPage-content">
          {this.renderToolbar(year, user)}
          {this.loading || this.loadingContent ? (
            <div className="RewindPage-contentLoading">
              <LoadingIndicator />
            </div>
          ) : this.communitySnapshot ? (
            this.renderCommunityStats()
          ) : (
            this.renderNoCommunity()
          )}
        </div>
      </PageStructure>
    );
  }

  renderHero(enabled: boolean, canModerate: boolean): Mithril.Children {
    return (
      <>
        {!enabled && canModerate && (
          <div className="RewindPage-adminNotice">
            <div className="container">
              <i className="fas fa-eye-slash"></i> {app.translator.trans('huseyinfiliz-rewind.forum.page.admin_notice')}
            </div>
          </div>
        )}
        <header className="Hero RewindHero">
          <div className="container">
            <div className="containerNarrow">
              <h1 className="Hero-title">
                <i className="fas fa-history"></i> {app.translator.trans('huseyinfiliz-rewind.forum.page.hero_title', { year: this.selectedYear })}
              </h1>
              <div className="Hero-subtitle">{app.translator.trans('huseyinfiliz-rewind.forum.page.hero_subtitle')}</div>
            </div>
          </div>
        </header>
      </>
    );
  }

  renderToolbar(year: number, user: any): Mithril.Children {
    const showYearSelector = this.availableYears.length > 1;
    const profileUrl = user ? app.route('huseyinfiliz-rewind.profile', { username: user.slug() }) : null;
    const isAdmin = app.session.user?.isAdmin();

    return (
      <div className="RewindPage-toolbar">
        {showYearSelector ? (
          <div className="RewindPage-yearSelector">
            {this.availableYears.map((y) => (
              <button
                key={y}
                className={'RewindPage-yearBtn' + (y === this.selectedYear ? ' active' : '')}
                onclick={() => this.switchYear(y)}
                disabled={this.loading || this.loadingContent}
              >
                {y}
              </button>
            ))}
          </div>
        ) : (
          <div />
        )}
        <div className="RewindPage-toolbarRight">
          {isAdmin && this.communitySnapshot && (
            <button className="Button RewindPage-myRewindBtn" onclick={() => this.confirmRegenerate()} disabled={this.generating}>
              <i className={this.generating ? 'fas fa-spinner fa-spin' : 'fas fa-sync'} />
              <span>{app.translator.trans('huseyinfiliz-rewind.forum.community.regenerate_short')}</span>
            </button>
          )}
          {user && profileUrl && (
            <Link className="Button RewindPage-myRewindBtn" href={profileUrl}>
              <i className="fas fa-user" />
              <span>{app.translator.trans('huseyinfiliz-rewind.forum.page.my_rewind')}</span>
            </Link>
          )}
        </div>
      </div>
    );
  }

  renderNoCommunity(): Mithril.Children {
    const isAdmin = app.session.user?.isAdmin();

    return (
      <div className="RewindPage-emptyState">
        <div className="RewindPage-emptyStateIcon">
          <i className="fas fa-chart-bar"></i>
        </div>
        <h3>{app.translator.trans('huseyinfiliz-rewind.forum.community.title')}</h3>
        {isAdmin ? (
          <div>
            <p>{app.translator.trans('huseyinfiliz-rewind.forum.community.not_generated')}</p>
            <Button
              className="Button Button--primary Button--block RewindPage-generateBtn"
              loading={this.generating}
              onclick={() => this.generateCommunity()}
              icon="fas fa-magic"
            >
              {app.translator.trans('huseyinfiliz-rewind.forum.community.generate_button')}
            </Button>
          </div>
        ) : (
          <p className="RewindPage-comingSoon">{app.translator.trans('huseyinfiliz-rewind.forum.community.coming_soon')}</p>
        )}
      </div>
    );
  }

  renderCommunityStats(): Mithril.Children {
    const data = this.communitySnapshot.snapshotData();
    if (!data) return null;

    const isAdmin = app.session.user?.isAdmin();

    // Check if community has any meaningful data
    const hasContent = !!(
      data.total_posts?.count > 0 ||
      data.total_discussions?.count > 0 ||
      data.new_users?.count > 0 ||
      data.top_tag?.name ||
      data.top_discussion?.id ||
      data.most_active_user?.username
    );

    return (
      <div className="RewindPage-community">
        {hasContent ? (
          <div
            className="RewindPage-slideshowBanner"
            onclick={() => {
              const renderMode = getYearRenderMode(this.selectedYear);
              if (renderMode === 'blade') {
                const baseUrl = app.forum.attribute('baseUrl') || '';
                window.location.href = `${baseUrl}/rewind/view/${this.selectedYear}`;
              } else {
                m.route.set(app.route('huseyinfiliz-rewind.community-slideshow', { year: this.selectedYear }));
              }
            }}
          >
            <div className="RewindPage-slideshowBannerBg" />
            <div className="RewindPage-slideshowBannerContent">
              <i className="fas fa-play-circle" />
              <div>
                <div className="RewindPage-slideshowBannerTitle">
                  {app.translator.trans('huseyinfiliz-rewind.forum.community.watch_slideshow', { year: this.selectedYear })}
                </div>
                <div className="RewindPage-slideshowBannerSub">
                  {app.translator.trans('huseyinfiliz-rewind.forum.community.watch_slideshow_desc')}
                </div>
              </div>
            </div>
          </div>
        ) : (
          <div className="RewindPage-emptyBanner">
            <i className="fas fa-ghost" />
            <span>{app.translator.trans('huseyinfiliz-rewind.forum.community.no_activity')}</span>
          </div>
        )}

        {hasContent && (
          <div className="RewindPage-sectionHeader">
            <h3>{app.translator.trans('huseyinfiliz-rewind.forum.community.title')}</h3>
          </div>
        )}

        <div className="RewindPage-statsGrid">
          {data.new_users?.count > 0 &&
            this.renderStatCard(
              'fas fa-user-plus',
              data.new_users.count,
              app.translator.trans('huseyinfiliz-rewind.forum.community.new_users') as string
            )}
          {data.total_posts?.count > 0 &&
            this.renderStatCard(
              'fas fa-comment',
              data.total_posts.count,
              app.translator.trans('huseyinfiliz-rewind.forum.community.total_posts') as string
            )}
          {data.total_discussions?.count > 0 &&
            this.renderStatCard(
              'fas fa-comments',
              data.total_discussions.count,
              app.translator.trans('huseyinfiliz-rewind.forum.community.total_discussions') as string
            )}
          {data.top_tag?.name &&
            this.renderStatCard('fas fa-tag', data.top_tag.name, app.translator.trans('huseyinfiliz-rewind.forum.community.top_tag') as string, true)}
          {data.top_discussion?.id &&
            this.renderWideCard(
              'fas fa-fire',
              app.translator.trans('huseyinfiliz-rewind.forum.community.top_discussion') as string,
              data.top_discussion.title,
              data.top_discussion.post_count
                ? (app.translator.trans('huseyinfiliz-rewind.forum.community.post_count_label', {
                    count: data.top_discussion.post_count,
                  }) as unknown as string)
                : undefined
            )}
          {data.most_active_user?.username &&
            this.renderWideCard(
              'fas fa-trophy',
              app.translator.trans('huseyinfiliz-rewind.forum.community.most_active_user') as string,
              data.most_active_user.username,
              data.most_active_user.post_count
                ? (app.translator.trans('huseyinfiliz-rewind.forum.community.post_count_label', {
                    count: data.most_active_user.post_count,
                  }) as unknown as string)
                : undefined
            )}
        </div>
      </div>
    );
  }

  renderStatCard(icon: string, value: string | number, label: string, isText = false): Mithril.Children {
    return (
      <div className="RewindPage-statCard">
        <div className="RewindPage-statCardIcon">
          <i className={icon}></i>
        </div>
        <div className={'RewindPage-statCardValue' + (isText ? ' RewindPage-statCardValue--text' : '')}>
          {typeof value === 'number' ? value.toLocaleString() : value}
        </div>
        <div className="RewindPage-statCardLabel">{label}</div>
      </div>
    );
  }

  renderWideCard(icon: string, label: string, title: string, subtitle?: string): Mithril.Children {
    return (
      <div className="RewindPage-statCard RewindPage-statCard--wide">
        <div className="RewindPage-statCardIcon">
          <i className={icon}></i>
        </div>
        <div className="RewindPage-statCardLabel">{label}</div>
        <div className="RewindPage-statCardValue RewindPage-statCardValue--text">{title}</div>
        {subtitle && <div className="RewindPage-statCardSubtitle">{subtitle}</div>}
      </div>
    );
  }
}
