import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { SaveSubmitEvent } from 'flarum/admin/components/AdminPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import Form from 'flarum/common/components/Form';
import GenerateModal from './GenerateModal';
import type { GenerateStep } from './GenerateModal';
import type Mithril from 'mithril';

type SlideSection = {
  key: string;
  icon: string;
  label: string;
  slides: string[];
};

const USER_SLIDE_SECTIONS: SlideSection[] = [
  {
    key: 'core',
    icon: 'fas fa-cube',
    label: 'huseyinfiliz-rewind.admin.settings.section_core',
    slides: ['post_count', 'discussion_count', 'active_days', 'word_count', 'most_active_month', 'top_words', 'night_owl'],
  },
  {
    key: 'content',
    icon: 'fas fa-trophy',
    label: 'huseyinfiliz-rewind.admin.settings.section_content',
    slides: ['best_post', 'top_emojis'],
  },
  {
    key: 'social',
    icon: 'fas fa-heart',
    label: 'huseyinfiliz-rewind.admin.settings.section_social',
    slides: ['top_tag', 'likes_given', 'likes_received', 'best_friend', 'best_friend_mentions', 'badges_earned', 'best_answers'],
  },
];

const COMMUNITY_SLIDE_SECTIONS: SlideSection[] = [
  {
    key: 'community_overview',
    icon: 'fas fa-cube',
    label: 'huseyinfiliz-rewind.admin.settings.section_core',
    slides: ['year-overview', 'busiest_month', 'peak_hour', 'total_words', 'new_members'],
  },
  {
    key: 'community_people',
    icon: 'fas fa-users',
    label: 'huseyinfiliz-rewind.admin.settings.section_people',
    slides: ['top_contributors', 'most_loved', 'most_active_user'],
  },
  {
    key: 'community_content',
    icon: 'fas fa-trophy',
    label: 'huseyinfiliz-rewind.admin.settings.section_content',
    slides: ['star_post', 'top_discussion', 'top_tag'],
  },
  {
    key: 'community_achievements',
    icon: 'fas fa-medal',
    label: 'huseyinfiliz-rewind.admin.settings.section_achievements',
    slides: ['best_answers_leaderboard', 'badge_leaderboard'],
  },
];

export default class RewindSettingsPage extends ExtensionPage {
  private activeTab: string = 'general';
  private slidesSubTab: string = 'user';
  private expandedSections: Set<string> = new Set(['core', 'community_overview']);
  private historicalYears: number[] = [];

  oninit(vnode: Mithril.Vnode<any, any>) {
    super.oninit(vnode);
    this.loadHistoricalYears();
  }

  async loadHistoricalYears() {
    try {
      const response = await app.request<{ years: Array<{ year: number }> }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-snaps/year-stats',
      });
      if (response && response.years) {
        this.historicalYears = response.years.map((y) => Number(y.year)).filter((y) => !isNaN(y) && y >= 2000 && y <= 2100);
        m.redraw();
      }
    } catch {
      // Gracefully fall back to local years
    }
  }

  saveSettings(e: SaveSubmitEvent) {
    const yearRaw = this.setting('huseyinfiliz-rewind.active_year')();
    const yearVal = Number(yearRaw);
    if (!yearVal || !Number.isInteger(yearVal) || yearVal < 2000 || yearVal > 2100) {
      app.alerts.show({ type: 'error' }, app.translator.trans('huseyinfiliz-rewind.admin.settings.invalid_active_year'));
      return Promise.reject();
    }
    return super.saveSettings(e);
  }

  content() {
    return (
      <div className="RewindSettings">
        <div className="RewindSettings-header">
          <div className="RewindSettings-tabs">
            {this.tabButton('general', 'fas fa-cog', 'huseyinfiliz-rewind.admin.tabs.general')}
            {this.tabButton('slides', 'fas fa-layer-group', 'huseyinfiliz-rewind.admin.tabs.slides')}
            {this.tabButton('templates', 'fas fa-code', 'huseyinfiliz-rewind.admin.tabs.templates')}
            {this.tabButton('advanced', 'fas fa-wrench', 'huseyinfiliz-rewind.admin.tabs.advanced')}
          </div>
        </div>
        <div className="RewindSettings-content">
          {this.activeTab === 'general' && this.generalTab()}
          {this.activeTab === 'slides' && this.slidesTab()}
          {this.activeTab === 'templates' && this.templatesTab()}
          {this.activeTab === 'advanced' && this.advancedTab()}
        </div>
      </div>
    );
  }

  tabButton(tab: string, iconClass: string, labelKey: string): Mithril.Children {
    return (
      <Button
        className={'Button ' + (this.activeTab === tab ? 'Button--primary' : '')}
        icon={iconClass}
        onclick={() => {
          this.activeTab = tab;
        }}
      >
        {app.translator.trans(labelKey)}
      </Button>
    );
  }

  generalTab(): Mithril.Children {
    return (
      <Form>
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huseyinfiliz-rewind.enabled',
            label: app.translator.trans('huseyinfiliz-rewind.admin.settings.enabled_label'),
          })}
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('huseyinfiliz-rewind.admin.settings.active_year_label')}</label>
          <input className="FormControl" type="number" min="2000" max="2100" step="1" bidi={this.setting('huseyinfiliz-rewind.active_year')} />
        </div>
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huseyinfiliz-rewind.show_menu_link',
            label: app.translator.trans('huseyinfiliz-rewind.admin.settings.show_menu_link_label'),
          })}
        </div>
        <div className="Form-group">
          {this.buildSettingComponent({
            type: 'boolean',
            setting: 'huseyinfiliz-rewind.community_comparison_enabled',
            label: app.translator.trans('huseyinfiliz-rewind.admin.settings.community_comparison_label'),
          })}
          <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.community_comparison_help')}</p>
        </div>
        <div className="Form-group">{this.submitButton()}</div>
      </Form>
    );
  }

  slidesTab(): Mithril.Children {
    return (
      <Form>
        <div className="RewindSettings-metricsPills">
          <button
            className={'RewindSettings-pill' + (this.slidesSubTab === 'user' ? ' active' : '')}
            onclick={() => {
              this.slidesSubTab = 'user';
            }}
            type="button"
          >
            <i className="fas fa-user" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.slides_user')}
          </button>
          <button
            className={'RewindSettings-pill' + (this.slidesSubTab === 'community' ? ' active' : '')}
            onclick={() => {
              this.slidesSubTab = 'community';
            }}
            type="button"
          >
            <i className="fas fa-users" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.slides_community')}
          </button>
        </div>
        <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.slides_help')}</p>
        {this.slidesSubTab === 'user' && this.renderSlideToggles(USER_SLIDE_SECTIONS, 'huseyinfiliz-rewind.hidden_user_slides', 'slide')}
        {this.slidesSubTab === 'community' &&
          this.renderSlideToggles(COMMUNITY_SLIDE_SECTIONS, 'huseyinfiliz-rewind.hidden_community_slides', 'community_slide')}
        <div className="Form-group">{this.submitButton()}</div>
      </Form>
    );
  }

  renderSlideToggles(sections: SlideSection[], settingKey: string, labelPrefix: string): Mithril.Children {
    const rawVal = this.setting(settingKey)();
    let hiddenSlides: string[] = [];
    try {
      hiddenSlides = JSON.parse(rawVal || '[]');
    } catch {
      hiddenSlides = [];
    }

    const toggle = (slide: string, visible: boolean) => {
      let current = [...hiddenSlides];
      if (visible) {
        current = current.filter((s) => s !== slide);
      } else {
        if (!current.includes(slide)) current.push(slide);
      }
      this.setting(settingKey)(JSON.stringify(current));
    };

    return (
      <div>
        {sections.map((section) => {
          const isExpanded = this.expandedSections.has(section.key);
          return (
            <div className={'RewindSettings-accordion' + (isExpanded ? ' RewindSettings-accordion--open' : '')} key={section.key}>
              <button className="RewindSettings-accordionHeader" type="button" onclick={() => this.toggleSection(section.key)}>
                <i className={section.icon + ' RewindSettings-accordionIcon'} />
                <span className="RewindSettings-accordionTitle">{app.translator.trans(section.label)}</span>
                <i className={'fas ' + (isExpanded ? 'fa-chevron-up' : 'fa-chevron-down') + ' RewindSettings-accordionChevron'} />
              </button>
              {isExpanded && (
                <div className="RewindSettings-accordionBody">
                  <div className="RewindSettings-metricsGrid">
                    {section.slides.map((slide) => (
                      <Switch key={slide} state={!hiddenSlides.includes(slide)} onchange={(val: boolean) => toggle(slide, val)}>
                        {app.translator.trans(`huseyinfiliz-rewind.admin.settings.${labelPrefix}_${slide}`)}
                      </Switch>
                    ))}
                  </div>
                </div>
              )}
            </div>
          );
        })}
      </div>
    );
  }

  toggleSection(key: string) {
    if (this.expandedSections.has(key)) {
      this.expandedSections.delete(key);
    } else {
      this.expandedSections.add(key);
    }
  }

  advancedTab(): Mithril.Children {
    return (
      <div>
        <div className="Form-group">
          <h3>{app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_community_title')}</h3>
          <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_community_help')}</p>
          <Button className="Button Button--primary" icon="fas fa-magic" onclick={() => this.openCommunityGenerateModal()}>
            {app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_community_button')}
          </Button>
        </div>
        <hr />
        <div className="Form-group">
          <h3>{app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_users_title')}</h3>
          <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_users_help')}</p>
          <Button className="Button Button--primary" icon="fas fa-users" onclick={() => this.openUserBatchGenerateModal()}>
            {app.translator.trans('huseyinfiliz-rewind.admin.settings.generate_users_button')}
          </Button>
        </div>
        <hr />
        <div className="Form-group">
          <h3>{app.translator.trans('huseyinfiliz-rewind.admin.settings.delete_rewinds_title')}</h3>
          <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.delete_rewinds_help')}</p>
          <Button className="Button Button--primary" icon="fas fa-trash" onclick={() => this.openDeleteRewindsModal()}>
            {app.translator.trans('huseyinfiliz-rewind.admin.settings.delete_rewinds_button')}
          </Button>
        </div>
      </div>
    );
  }

  async loadGroupOptions(): Promise<Array<{ value: any; label: string }>> {
    const options: Array<{ value: any; label: string }> = [
      { value: '', label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.all_users') as string },
    ];
    try {
      const response = await app.request<{ groups: Array<{ id: number; namePlural: string; count: number }> }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-snaps/groups',
      });
      for (const g of response.groups || []) {
        options.push({ value: String(g.id), label: `${g.namePlural} (${g.count})` });
      }
    } catch (e) {
      console.error('Failed to load group options', e);
    }
    return options;
  }

  async loadYearOptions(): Promise<Array<{ value: any; label: string }>> {
    try {
      const response = await app.request<{ years: Array<{ year: number; count: number }> }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rw-snaps/year-stats',
      });
      return (response.years || []).map((y) => ({
        value: String(y.year),
        label: `${y.year} (${y.count} users)`,
      }));
    } catch (e) {
      console.error('Failed to load year options', e);
      return [];
    }
  }

  openCommunityGenerateModal() {
    const activeYear = parseInt(app.data.settings['huseyinfiliz-rewind.active_year'] as string, 10) || new Date().getFullYear();

    app.modal.show(GenerateModal, {
      title: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.community_title') as string,
      configFields: [
        {
          type: 'number' as const,
          key: 'year',
          label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.year_label') as string,
          value: activeYear,
        },
      ],
      loadSteps: async (): Promise<GenerateStep[]> => {
        const response = await app.request<{ steps: string[] }>({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/rw-community/generate-steps',
        });
        return (response.steps || []).map((key) => ({ key, label: key.replace(/_/g, ' ') }));
      },
      executeStep: async (step: GenerateStep, config: Record<string, any>) => {
        await app.request({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/rw-community/generate-step',
          body: { step: step.key, year: config.year },
        });
      },
    });
  }

  openUserBatchGenerateModal() {
    const activeYear = parseInt(app.data.settings['huseyinfiliz-rewind.active_year'] as string, 10) || new Date().getFullYear();

    app.modal.show(GenerateModal, {
      title: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.users_title') as string,
      configFields: [
        {
          type: 'number' as const,
          key: 'year',
          label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.year_label') as string,
          value: activeYear,
        },
        {
          type: 'select' as const,
          key: 'group',
          label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.group_label') as string,
          value: '',
          loadOptions: () => this.loadGroupOptions(),
        },
      ],
      loadSteps: async (config: Record<string, any>): Promise<GenerateStep[]> => {
        const body: Record<string, any> = { year: config.year };
        if (config.group) body.group = parseInt(config.group, 10);
        const response = await app.request<{ users: Array<{ id: number; username: string }> }>({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/rw-snaps/missing-users',
          body,
        });
        return (response.users || []).map((u) => ({ key: String(u.id), label: u.username }));
      },
      executeStep: async (step: GenerateStep, config: Record<string, any>) => {
        await app.request({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/rw-snaps/generate-for-user',
          body: { userId: parseInt(step.key, 10), year: config.year },
        });
      },
    });
  }

  openDeleteRewindsModal() {
    app.modal.show(GenerateModal, {
      title: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.delete_title') as string,
      confirmMode: true,
      confirmLabel: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.delete_confirm') as string,
      configFields: [
        {
          type: 'select' as const,
          key: 'year',
          label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.year_label') as string,
          value: '',
          loadOptions: () => this.loadYearOptions(),
        },
        {
          type: 'select' as const,
          key: 'group',
          label: app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.group_label') as string,
          value: '',
          loadOptions: () => this.loadGroupOptions(),
        },
      ],
      loadSteps: async () => [],
      executeStep: async () => {},
      onConfirm: async (config: Record<string, any>) => {
        const response = await app.request<{ deleted: number }>({
          method: 'POST',
          url: app.forum.attribute('apiUrl') + '/rw-snaps/batch-delete',
          body: { year: parseInt(config.year, 10), group: config.group ? parseInt(config.group, 10) : null },
        });
        return response.deleted;
      },
    });
  }

  templatesTab(): Mithril.Children {
    const currentYearStr = String(new Date().getFullYear());
    const activeYear = parseInt(this.setting('huseyinfiliz-rewind.active_year')() || currentYearStr, 10);

    return (
      <Form>
        <div className="RewindSettings-templates">
          <div className="RewindSettings-infoCard">
            <div className="RewindSettings-infoCardHeader">
              <i className="fas fa-magic" />
              <div>
                <h4>{app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_title')}</h4>
                <p>{app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_help')}</p>
              </div>
            </div>
          </div>

          <div className="RewindSettings-section">
            <h3>
              <i className="fas fa-folder-open" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_storage_locations')}
            </h3>
            <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_storage_help')}</p>

            <div className="RewindSettings-pathsList">
              <div className="RewindSettings-pathItem" onclick={() => this.copyToClipboard(`storage/rewind/views/user_${activeYear}.blade.php`)}>
                <span className="RewindSettings-pathBadge">User (Year-Specific)</span>
                <code>{`storage/rewind/views/user_${activeYear}.blade.php`}</code>
                <i className="fas fa-copy RewindSettings-copyIcon" title="Click to copy" />
              </div>
              <div className="RewindSettings-pathItem" onclick={() => this.copyToClipboard('storage/rewind/views/user.blade.php')}>
                <span className="RewindSettings-pathBadge">User (Default Fallback)</span>
                <code>storage/rewind/views/user.blade.php</code>
                <i className="fas fa-copy RewindSettings-copyIcon" title="Click to copy" />
              </div>
              <div className="RewindSettings-pathItem" onclick={() => this.copyToClipboard(`storage/rewind/views/community_${activeYear}.blade.php`)}>
                <span className="RewindSettings-pathBadge">Community (Year-Specific)</span>
                <code>{`storage/rewind/views/community_${activeYear}.blade.php`}</code>
                <i className="fas fa-copy RewindSettings-copyIcon" title="Click to copy" />
              </div>
              <div className="RewindSettings-pathItem" onclick={() => this.copyToClipboard('storage/rewind/views/community.blade.php')}>
                <span className="RewindSettings-pathBadge">Community (Default Fallback)</span>
                <code>storage/rewind/views/community.blade.php</code>
                <i className="fas fa-copy RewindSettings-copyIcon" title="Click to copy" />
              </div>
            </div>
          </div>

          <div className="RewindSettings-section">
            <h3>
              <i className="fas fa-calendar-alt" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.year_presentation_modes_title')}
            </h3>
            <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.year_presentation_modes_help')}</p>

            <div className="RewindSettings-yearModesTable">
              {this.getAllDisplayYears().map((year) => {
                const yearModes = this.getYearModes();
                const currentMode = yearModes[String(year)] || '';

                return (
                  <div className="RewindSettings-yearModeRow">
                    <div className="RewindSettings-yearModeYear">
                      <strong>{year}</strong>
                      {parseInt(this.setting('huseyinfiliz-rewind.active_year')() || currentYearStr, 10) === year && (
                        <span className="RewindSettings-pathBadge">Active</span>
                      )}
                    </div>
                    <Select
                      value={currentMode || 'slideshow'}
                      options={{
                        slideshow: app.translator.trans('huseyinfiliz-rewind.admin.settings.render_mode_slideshow') as string,
                        blade: app.translator.trans('huseyinfiliz-rewind.admin.settings.render_mode_blade') as string,
                      }}
                      onchange={(mode: string) => this.setYearMode(year, mode)}
                    />
                  </div>
                );
              })}
            </div>
          </div>

          <div className="Form-group">{this.submitButton()}</div>

          <div className="RewindSettings-section">
            <h3>
              <i className="fas fa-code" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_variables_title')}
            </h3>
            <p className="helpText">{app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_variables_help')}</p>

            <div className="RewindSettings-varsGrid">
              {/* User Info & Actor */}
              <div className="RewindSettings-varCard">
                <div className="RewindSettings-varCardTitle">
                  <i className="fas fa-user" /> User & Forum Context
                </div>
                <div className="RewindSettings-varList">
                  {this.renderVarRow('$user->username', 'Username handle')}
                  {this.renderVarRow('$user->display_name', 'Display name')}
                  {this.renderVarRow('$user->avatar_url', 'Avatar image URL')}
                  {this.renderVarRow('$user->id', 'User ID number')}
                  {this.renderVarRow('$year', 'Active rewind year')}
                  {this.renderVarRow('$forumTitle', 'Forum title string')}
                  {this.renderVarRow('$isOwner', 'True if viewer is owner')}
                  {this.renderVarRow('$canModerate', 'True if viewer is admin/moderator')}
                </div>
              </div>

              {/* Core User Metrics */}
              <div className="RewindSettings-varCard">
                <div className="RewindSettings-varCardTitle">
                  <i className="fas fa-chart-bar" /> User Activity Metrics
                </div>
                <div className="RewindSettings-varList">
                  {this.renderVarRow("$metrics['post_count']['count']", 'Total posts created')}
                  {this.renderVarRow("$metrics['discussion_count']['count']", 'Total discussions started')}
                  {this.renderVarRow("$metrics['active_days']['count']", 'Total active days in year')}
                  {this.renderVarRow("$metrics['word_count']['count']", 'Total words written')}
                  {this.renderVarRow("$metrics['most_active_month']['peak_month']", 'Peak active month (1-12)')}
                  {this.renderVarRow("$metrics['most_active_month']['peak_count']", 'Posts in peak month')}
                  {this.renderVarRow("$metrics['night_owl']['peak_hour']", 'Most active hour (0-23)')}
                  {this.renderVarRow("$metrics['night_owl']['is_night_owl']", 'True if active at night')}
                </div>
              </div>

              {/* Social & Content */}
              <div className="RewindSettings-varCard">
                <div className="RewindSettings-varCardTitle">
                  <i className="fas fa-heart" /> Social & Content
                </div>
                <div className="RewindSettings-varList">
                  {this.renderVarRow("$metrics['best_post']['content_html']", 'HTML content of top post')}
                  {this.renderVarRow("$metrics['best_post']['discussion_title']", 'Title of thread')}
                  {this.renderVarRow("$metrics['best_post']['count']", 'Likes/reactions count')}
                  {this.renderVarRow("$metrics['top_tag']['tag_name']", 'Top tag name')}
                  {this.renderVarRow("$metrics['top_tag']['tag_color']", 'Tag color code')}
                  {this.renderVarRow("$metrics['best_friend']['display_name']", 'Top collaborator name')}
                  {this.renderVarRow("$metrics['best_friend']['avatar_url']", 'Collaborator avatar URL')}
                  {this.renderVarRow("$metrics['likes_received']['count']", 'Total likes received')}
                  {this.renderVarRow("$metrics['likes_given']['count']", 'Total likes given')}
                  {this.renderVarRow("$metrics['best_answers']['count']", 'Best answers solved')}
                  {this.renderVarRow("$metrics['badges_earned']['count']", 'Badges earned')}
                </div>
              </div>

              {/* Community Metrics */}
              <div className="RewindSettings-varCard">
                <div className="RewindSettings-varCardTitle">
                  <i className="fas fa-users" /> Community Metrics
                </div>
                <div className="RewindSettings-varList">
                  {this.renderVarRow("$metrics['new_users']['count']", 'New members registered')}
                  {this.renderVarRow("$metrics['total_posts']['count']", 'Forum-wide total posts')}
                  {this.renderVarRow("$metrics['total_discussions']['count']", 'Forum-wide discussions')}
                  {this.renderVarRow("$metrics['total_words']['total_words']", 'Total words across forum')}
                  {this.renderVarRow("$metrics['busiest_month']['peak_month']", 'Community busiest month')}
                  {this.renderVarRow("$metrics['peak_hour']['peak_hour']", 'Community peak hour (0-23)')}
                  {this.renderVarRow("$metrics['top_tag']['name']", 'Most used tag')}
                  {this.renderVarRow("$metrics['top_discussion']['title']", 'Most active discussion')}
                  {this.renderVarRow("$metrics['most_active_user']['username']", 'Top member username')}
                  {this.renderVarRow("$metrics['most_loved']['username']", 'Most loved member')}
                </div>
              </div>
            </div>
          </div>

          <div className="RewindSettings-section">
            <h3>
              <i className="fas fa-terminal" /> {app.translator.trans('huseyinfiliz-rewind.admin.settings.templates_examples_title')}
            </h3>

            <div className="RewindSettings-snippetBox">
              <div className="RewindSettings-snippetHeader">
                <span>Example 1: Displaying a Stat Card with Blade</span>
                <button
                  className="Button Button--link"
                  onclick={() =>
                    this.copyToClipboard(
                      `<div class="stat-card">\n    <h3>{{ $user->display_name }}'s {{ $year }} Stats</h3>\n    <p>You wrote <strong>{{ number_format($metrics['post_count']['count'] ?? 0) }}</strong> posts!</p>\n</div>`
                    )
                  }
                >
                  <i className="fas fa-copy" /> Copy
                </button>
              </div>
              <pre className="RewindSettings-codeBlock">
                {`<div class="stat-card">
    <h3>{{ $user->display_name }}'s {{ $year }} Stats</h3>
    <p>You wrote <strong>{{ number_format($metrics['post_count']['count'] ?? 0) }}</strong> posts!</p>
</div>`}
              </pre>
            </div>

            <div className="RewindSettings-snippetBox">
              <div className="RewindSettings-snippetHeader">
                <span>Example 2: Looping through Top Words / Emojis</span>
                <button
                  className="Button Button--link"
                  onclick={() =>
                    this.copyToClipboard(
                      `@foreach($metrics['top_words']['words'] ?? [] as $w)\n    <span class="badge">{{ $w['word'] }} ({{ $w['count'] }})</span>\n@endforeach`
                    )
                  }
                >
                  <i className="fas fa-copy" /> Copy
                </button>
              </div>
              <pre className="RewindSettings-codeBlock">
                {`@foreach($metrics['top_words']['words'] ?? [] as $w)
    <span class="badge">{{ $w['word'] }} ({{ $w['count'] }})</span>
@endforeach`}
              </pre>
            </div>

            <div className="RewindSettings-snippetBox">
              <div className="RewindSettings-snippetHeader">
                <span>Example 3: Conditional Collaborator Highlight</span>
                <button
                  className="Button Button--link"
                  onclick={() =>
                    this.copyToClipboard(
                      `@if(!empty($metrics['best_friend']['username']))\n    <div class="best-friend">\n        <span>Best Friend: {{ $metrics['best_friend']['display_name'] }}</span>\n        <span>{{ $metrics['best_friend']['interaction_count'] }} interactions</span>\n    </div>\n@endif`
                    )
                  }
                >
                  <i className="fas fa-copy" /> Copy
                </button>
              </div>
              <pre className="RewindSettings-codeBlock">
                {`@if(!empty($metrics['best_friend']['username']))
    <div class="best-friend">
        <span>Best Friend: {{ $metrics['best_friend']['display_name'] }}</span>
        <span>{{ $metrics['best_friend']['interaction_count'] }} interactions</span>
    </div>
@endif`}
              </pre>
            </div>
          </div>
        </div>
      </Form>
    );
  }

  renderVarRow(variable: string, desc: string): Mithril.Children {
    return (
      <div className="RewindSettings-varRow" onclick={() => this.copyToClipboard(`{{ ${variable} }}`)}>
        <code className="RewindSettings-varCode">{variable}</code>
        <span className="RewindSettings-varDesc">{desc}</span>
        <i className="fas fa-copy RewindSettings-varCopy" title="Copy Blade tag" />
      </div>
    );
  }

  copyToClipboard(text: string) {
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(() => {
        app.alerts.show({ type: 'success' }, `Copied: ${text}`);
      });
    }
  }

  getYearModes(): Record<string, string> {
    const raw = this.setting('huseyinfiliz-rewind.year_render_modes')();
    try {
      return typeof raw === 'string' ? JSON.parse(raw || '{}') : raw || {};
    } catch {
      return {};
    }
  }

  setYearMode(year: string | number, mode: string) {
    const modes = { ...this.getYearModes() };
    if (!mode || mode === 'slideshow') {
      delete modes[String(year)];
    } else {
      modes[String(year)] = mode;
    }
    this.setting('huseyinfiliz-rewind.year_render_modes')(JSON.stringify(modes));
    m.redraw();
  }

  getAllDisplayYears(): number[] {
    const activeYear = parseInt(this.setting('huseyinfiliz-rewind.active_year')() || String(new Date().getFullYear()), 10);
    const set = new Set<number>([activeYear]);

    this.historicalYears.forEach((y) => {
      set.add(y);
    });

    const modes = this.getYearModes();
    Object.keys(modes).forEach((y) => {
      const parsed = parseInt(y, 10);
      if (!isNaN(parsed) && parsed >= 2000 && parsed <= 2100) {
        set.add(parsed);
      }
    });
    return Array.from(set).sort((a, b) => b - a);
  }
}
