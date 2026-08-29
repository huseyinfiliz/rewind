import app from 'flarum/forum/app';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { getYearRenderMode } from '../../common/utils/getYearRenderMode';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';

export default class ProfileRewindPage extends UserPage {
  loading = true;
  generating = false;
  snapshots: any[] = [];

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.loadUser(m.route.param('username'));
  }

  show(user: User) {
    super.show(user);
    this.loadSnapshots(user);
  }

  isOwner(): boolean {
    return !!(app.session.user && this.user && app.session.user.id() === this.user.id());
  }

  async loadSnapshots(user: User) {
    this.loading = true;
    m.redraw();

    try {
      const response = await app.store.find('rw-snaps', {
        filter: { user: user.id() },
        sort: '-year',
        fields: { 'rw-snaps': 'year,generatedAt,isPublic,canEdit,canModerate,isEmpty' },
      } as any);
      this.snapshots = Array.isArray(response) ? response : [response];
    } catch {
      this.snapshots = [];
    }

    this.loading = false;
    m.redraw();
  }

  openSlideshow(snapshot: any) {
    const renderMode = getYearRenderMode(snapshot.year());
    if (renderMode === 'blade') {
      const baseUrl = app.forum.attribute('baseUrl') || '';
      window.location.href = `${baseUrl}/rewind/view/${this.user!.id()}/${snapshot.year()}`;
    } else {
      m.route.set(app.route('huseyinfiliz-rewind.slideshow', { id: this.user!.id(), year: snapshot.year() }));
    }
  }

  async generateAndOpen() {
    this.generating = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-snaps/generate',
      });

      const year = app.forum.attribute<number>('rewindActiveYear');
      const refetched = await app.store.find('rw-snaps', {
        filter: { user: this.user!.id(), year },
      } as any);
      const results = Array.isArray(refetched) ? refetched : [refetched];
      const snapshot = results.length > 0 ? results[0] : null;

      await this.loadSnapshots(this.user!);

      if (snapshot) {
        this.openSlideshow(snapshot);
        return;
      }
    } catch (e) {
      const msg = (e as any)?.responseJSON?.errors?.[0]?.detail || 'Failed to generate rewind';
      app.alerts.show({ type: 'error' }, msg);
    }

    this.generating = false;
    m.redraw();
  }

  savingPublic = false;

  async togglePublic(snapshot: any) {
    if (!snapshot || this.savingPublic) return;

    this.savingPublic = true;
    m.redraw();

    try {
      await snapshot.save({ isPublic: !snapshot.isPublic() });
    } catch (e) {
      console.error('Failed to toggle visibility:', e);
    }

    this.savingPublic = false;
    m.redraw();
  }

  async regenerateSnapshot(snapshot: any) {
    this.generating = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-snaps/generate',
      });

      await this.loadSnapshots(this.user!);
    } catch (e) {
      const msg = (e as any)?.responseJSON?.errors?.[0]?.detail || 'Failed to regenerate';
      app.alerts.show({ type: 'error' }, msg);
    }

    this.generating = false;
    m.redraw();
  }

  async deleteSnapshot(snapshot: any) {
    if (!confirm(app.translator.trans('huseyinfiliz-rewind.forum.profile.controls.confirm_delete') as string)) {
      return;
    }

    try {
      await snapshot.delete();
      this.snapshots = this.snapshots.filter((s: any) => s.id() !== snapshot.id());
      m.redraw();
    } catch (e) {
      const msg = (e as any)?.responseJSON?.errors?.[0]?.detail || 'Failed to delete';
      app.alerts.show({ type: 'error' }, msg);
    }
  }

  content(): Mithril.Children {
    const canView = app.forum.attribute('canViewRewind');
    const canModerate = app.forum.attribute('canModerateRewind');
    const enabled = app.forum.attribute('rewindEnabled');

    if (!(enabled && canView) && !canModerate) {
      return (
        <div className="RewindView-empty">
          <p>{app.translator.trans('huseyinfiliz-rewind.forum.page.not_available')}</p>
        </div>
      );
    }

    if (this.loading || this.generating) {
      return (
        <div className="RewindView-loading">
          <LoadingIndicator />
          {this.generating && <p>{app.translator.trans('huseyinfiliz-rewind.forum.page.generating')}</p>}
        </div>
      );
    }

    return this.renderGrid();
  }

  renderGrid(): Mithril.Children {
    const user = this.user!;
    const isOwner = this.isOwner();
    const year = app.forum.attribute<number>('rewindActiveYear');
    const hasActiveYear = this.snapshots.some((s: any) => s.year() === year);

    const canGenerate = app.forum.attribute('canGenerateRewind');

    return (
      <div className="RewindGrid">
        <div className="RewindGrid-cards">
          {isOwner && !hasActiveYear && canGenerate && (
            <div className="RewindGrid-card RewindGrid-card--generate" onclick={() => this.generateAndOpen()}>
              <div className="RewindGrid-cardLink">
                <div className="RewindGrid-cardIcon RewindGrid-cardIcon--generate">
                  <i className="fas fa-play" />
                </div>
                <div className="RewindGrid-cardBody">
                  <div className="RewindGrid-cardYear">{year}</div>
                  <div className="RewindGrid-cardMeta">
                    <span className="RewindGrid-cardBadge generate">{app.translator.trans('huseyinfiliz-rewind.forum.profile.tap_to_watch')}</span>
                  </div>
                </div>
              </div>
            </div>
          )}
          {isOwner && !hasActiveYear && !canGenerate && (
            <div className="RewindGrid-card RewindGrid-card--locked">
              <div className="RewindGrid-cardLink">
                <div className="RewindGrid-cardIcon RewindGrid-cardIcon--locked">
                  <i className="fas fa-lock" />
                </div>
                <div className="RewindGrid-cardBody">
                  <div className="RewindGrid-cardYear">{year}</div>
                  <div className="RewindGrid-cardMeta">
                    <span className="RewindGrid-cardBadge locked">{app.translator.trans('huseyinfiliz-rewind.forum.profile.not_yet_available')}</span>
                  </div>
                </div>
              </div>
            </div>
          )}
          {!isOwner && !hasActiveYear && (
            <div className="RewindGrid-card RewindGrid-card--locked">
              <div className="RewindGrid-cardLink">
                <div className="RewindGrid-cardIcon RewindGrid-cardIcon--locked">
                  <i className="fas fa-lock" />
                </div>
                <div className="RewindGrid-cardBody">
                  <div className="RewindGrid-cardYear">{year}</div>
                  <div className="RewindGrid-cardMeta">
                    <span className="RewindGrid-cardBadge locked">{app.translator.trans('huseyinfiliz-rewind.forum.profile.not_yet_available')}</span>
                  </div>
                </div>
              </div>
            </div>
          )}
          {this.snapshots.map((snapshot: any) => {
            const empty = snapshot.isEmpty();
            const isPrivate = !snapshot.isPublic();
            const canView = isOwner || !isPrivate || snapshot.canModerate?.();
            const locked = empty || (!canView && isPrivate);

            return (
              <div className={'RewindGrid-card' + (locked ? ' RewindGrid-card--locked' : '')} key={snapshot.id()}>
                {locked ? (
                  <div className="RewindGrid-cardLink">
                    <div className="RewindGrid-cardIcon RewindGrid-cardIcon--locked">
                      <i className={empty ? 'fas fa-ghost' : 'fas fa-lock'} />
                    </div>
                    <div className="RewindGrid-cardBody">
                      <div className="RewindGrid-cardYear">{snapshot.year()}</div>
                      <div className="RewindGrid-cardMeta">
                        <span className="RewindGrid-cardBadge locked">
                          {empty
                            ? app.translator.trans('huseyinfiliz-rewind.forum.profile.no_activity')
                            : app.translator.trans('huseyinfiliz-rewind.forum.profile.private_badge')}
                        </span>
                      </div>
                    </div>
                  </div>
                ) : (
                  <a
                    className="RewindGrid-cardLink"
                    href="#"
                    onclick={(e: Event) => {
                      e.preventDefault();
                      this.openSlideshow(snapshot);
                    }}
                  >
                    <div className="RewindGrid-cardIcon">
                      <i className="fas fa-history" />
                    </div>
                    <div className="RewindGrid-cardBody">
                      <div className="RewindGrid-cardYear">{snapshot.year()}</div>
                      <div className="RewindGrid-cardMeta">
                        <span className={`RewindGrid-cardBadge ${isPrivate ? 'private' : 'public'}`}>
                          <i className={isPrivate ? 'fas fa-lock' : 'fas fa-globe-americas'} />
                          {isPrivate
                            ? app.translator.trans('huseyinfiliz-rewind.forum.profile.private_badge')
                            : app.translator.trans('huseyinfiliz-rewind.forum.profile.public_badge')}
                        </span>
                      </div>
                    </div>
                  </a>
                )}
                {snapshot.canEdit() && (
                  <Dropdown className="RewindGrid-cardControls" buttonClassName="Button Button--icon Button--flat" icon="fas fa-ellipsis-v">
                    <Button icon={snapshot.isPublic() ? 'fas fa-eye-slash' : 'fas fa-eye'} onclick={() => this.togglePublic(snapshot)}>
                      {snapshot.isPublic()
                        ? app.translator.trans('huseyinfiliz-rewind.forum.profile.controls.make_private')
                        : app.translator.trans('huseyinfiliz-rewind.forum.profile.controls.make_public')}
                    </Button>
                    {snapshot.canModerate() && (
                      <Button icon="fas fa-sync" onclick={() => this.regenerateSnapshot(snapshot)}>
                        {app.translator.trans('huseyinfiliz-rewind.forum.profile.controls.regenerate')}
                      </Button>
                    )}
                    {snapshot.canModerate() && (
                      <Button icon="fas fa-trash" onclick={() => this.deleteSnapshot(snapshot)}>
                        {app.translator.trans('huseyinfiliz-rewind.forum.profile.controls.delete')}
                      </Button>
                    )}
                  </Dropdown>
                )}
              </div>
            );
          })}
        </div>
      </div>
    );
  }
}
