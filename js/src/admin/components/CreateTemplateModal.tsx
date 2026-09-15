import app from 'flarum/admin/app';
import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

export type CustomTemplate = {
  id: string;
  type: 'user' | 'community' | 'error';
  year: number | null;
  filename: string;
  title: string;
  size: number;
  modifiedAt: string;
  isCustom: boolean;
  content?: string;
};

export interface CreateTemplateModalAttrs extends IFormModalAttrs {
  existingIds: string[];
  activeYear: number;
  onCreated: (template: CustomTemplate) => void;
}

export default class CreateTemplateModal extends FormModal<CreateTemplateModalAttrs> {
  type: 'user' | 'community' | 'error' = 'user';
  scope: 'year' | 'default' = 'year';
  year: number = 2026;
  errorMessage: string = '';

  oninit(vnode: Mithril.Vnode<CreateTemplateModalAttrs>) {
    super.oninit(vnode);
    this.year = this.attrs.activeYear || new Date().getFullYear();
  }

  className(): string {
    return 'Modal--small RewindCreateTemplateModal';
  }

  title(): Mithril.Children {
    return app.translator.trans('huseyinfiliz-rewind.admin.templates.create_modal_title');
  }

  getTargetId(): string {
    if (this.type === 'error') {
      return 'error';
    }
    if (this.scope === 'year') {
      return `${this.type}_${this.year}`;
    }
    return this.type;
  }

  getTargetFilename(): string {
    return `${this.getTargetId()}.blade.php`;
  }

  alreadyExists(): boolean {
    return this.attrs.existingIds.includes(this.getTargetId());
  }

  isValid(): boolean {
    if (this.type === 'error') {
      return true;
    }
    if (this.scope === 'year') {
      return Number.isInteger(this.year) && this.year >= 2000 && this.year <= 2100;
    }
    return true;
  }

  content(): Mithril.Children {
    const filename = this.getTargetFilename();
    const exists = this.alreadyExists();

    return (
      <div className="Modal-body">
        <div className="Form">
          {this.errorMessage && (
            <div className="Alert Alert--error" style={{ marginBottom: '16px' }}>
              <div className="Alert-body">{this.errorMessage}</div>
            </div>
          )}

          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-rewind.admin.templates.type_label')}</label>
            <Select
              value={this.type}
              options={{
                user: app.translator.trans('huseyinfiliz-rewind.admin.templates.type_user') as string,
                community: app.translator.trans('huseyinfiliz-rewind.admin.templates.type_community') as string,
                error: app.translator.trans('huseyinfiliz-rewind.admin.templates.type_error') as string,
              }}
              onchange={(val: 'user' | 'community' | 'error') => {
                this.type = val;
                if (val === 'error') {
                  this.scope = 'default';
                }
              }}
            />
          </div>

          {this.type !== 'error' && (
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-rewind.admin.templates.scope_label')}</label>
              <Select
                value={this.scope}
                options={{
                  year: app.translator.trans('huseyinfiliz-rewind.admin.templates.scope_year_title') as string,
                  default: app.translator.trans('huseyinfiliz-rewind.admin.templates.scope_default_title') as string,
                }}
                onchange={(val: 'year' | 'default') => {
                  this.scope = val;
                }}
              />
              <p className="helpText" style={{ marginTop: '4px' }}>
                {this.scope === 'year'
                  ? app.translator.trans('huseyinfiliz-rewind.admin.templates.scope_year')
                  : app.translator.trans('huseyinfiliz-rewind.admin.templates.scope_default')}
              </p>
            </div>
          )}

          {this.type !== 'error' && this.scope === 'year' && (
            <div className="Form-group">
              <label>{app.translator.trans('huseyinfiliz-rewind.admin.templates.year_label')}</label>
              <input
                className="FormControl"
                type="number"
                min="2000"
                max="2100"
                step="1"
                value={this.year}
                oninput={(e: InputEvent) => {
                  this.year = parseInt((e.target as HTMLInputElement).value, 10) || this.year;
                }}
              />
            </div>
          )}

          <div className="Form-group">
            <label>{app.translator.trans('huseyinfiliz-rewind.admin.templates.target_file')}</label>
            <p className="helpText" style={{ margin: '0 0 6px 0' }}>
              <code>storage/rewind/views/{filename}</code>
            </p>
            {exists ? (
              <p className="helpText" style={{ color: 'var(--alert-error-color, #ef4444)', fontWeight: 600, margin: 0 }}>
                <i className="fas fa-exclamation-circle" /> {app.translator.trans('huseyinfiliz-rewind.admin.templates.already_exists')}
              </p>
            ) : (
              <p className="helpText" style={{ margin: 0 }}>
                {app.translator.trans('huseyinfiliz-rewind.admin.templates.create_help')}
              </p>
            )}
          </div>

          <div className="Form-group" style={{ display: 'flex', justifyContent: 'flex-end', gap: '8px', marginTop: '20px' }}>
            <Button
              type="button"
              className="Button"
              onclick={() => {
                this.hide();
              }}
              disabled={this.loading}
            >
              {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.cancel')}
            </Button>
            <Button type="submit" className="Button Button--primary" loading={this.loading} disabled={exists || !this.isValid()} icon="fas fa-plus">
              {app.translator.trans('huseyinfiliz-rewind.admin.templates.create_submit')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  onsubmit(e: SubmitEvent) {
    e.preventDefault();
    if (this.alreadyExists() || !this.isValid() || this.loading) {
      return;
    }

    this.loading = true;
    this.errorMessage = '';
    m.redraw();

    const body: Record<string, any> = {
      type: this.type,
      year: this.scope === 'year' && this.type !== 'error' ? this.year : null,
    };

    app
      .request<{ template: CustomTemplate }>({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/rewind-templates',
        body,
      })
      .then((response) => {
        this.loading = false;
        this.hide();
        if (response && response.template) {
          this.attrs.onCreated(response.template);
          app.alerts.show({ type: 'success' }, app.translator.trans('huseyinfiliz-rewind.admin.templates.created_success'));
        }
      })
      .catch((err) => {
        this.loading = false;
        this.errorMessage = err?.response?.error || 'Failed to create template.';
        m.redraw();
      });
  }
}
