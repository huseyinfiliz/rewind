import app from 'flarum/admin/app';
import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import getActiveYear from '../utils/activeYear';

export type GenerateStep = {
  key: string;
  label: string;
};

export type ConfigField = {
  type: 'number' | 'select';
  key: string;
  label: string;
  value: any;
  options?: Array<{ value: any; label: string }>;
  loadOptions?: () => Promise<Array<{ value: any; label: string }>>;
};

export type GenerateModalAttrs = IInternalModalAttrs & {
  title: string;
  configFields?: ConfigField[];
  loadSteps: (config: Record<string, any>) => Promise<GenerateStep[]>;
  executeStep: (step: GenerateStep, config: Record<string, any>) => Promise<void>;
  onComplete?: () => void;
  /** If true, show a confirmation step instead of executing steps */
  confirmMode?: boolean;
  confirmLabel?: string;
  onConfirm?: (config: Record<string, any>) => Promise<number>;
};

type Phase = 'loading' | 'config' | 'running' | 'done';

export default class GenerateModal extends Modal<GenerateModalAttrs> {
  phase: Phase = 'loading';
  steps: GenerateStep[] = [];
  currentIndex = -1;
  failed = false;
  errorMessage = '';
  config: Record<string, any> = {};
  resolvedFields: ConfigField[] = [];
  deleteCount = 0;
  private beforeUnloadHandler: ((e: BeforeUnloadEvent) => void) | null = null;

  oninit(vnode: Mithril.Vnode<GenerateModalAttrs>) {
    super.oninit(vnode);
    const attrs = this.attrs;

    if (attrs.configFields) {
      for (const field of attrs.configFields) {
        this.config[field.key] = field.value;
      }
    }

    if (!attrs.configFields || attrs.configFields.length === 0) {
      this.phase = 'running';
      this.start();
    } else {
      this.loadConfigOptions();
    }
  }

  async loadConfigOptions() {
    const attrs = this.attrs;
    const fields = attrs.configFields || [];

    try {
      this.resolvedFields = await Promise.all(
        fields.map(async (field) => {
          if (field.loadOptions && !field.options) {
            const options = await field.loadOptions();
            return { ...field, options };
          }
          return field;
        })
      );

      // Auto-select first option for select fields with no initial value
      for (const field of this.resolvedFields) {
        if (field.type === 'select' && !this.config[field.key] && field.options?.length) {
          this.config[field.key] = field.options[0].value;
        }
      }

      this.phase = 'config';
    } catch (e: any) {
      this.failed = true;
      this.errorMessage = e?.message || 'Failed to load options';
      this.phase = 'done';
    }
    m.redraw();
  }

  className() {
    return 'GenerateModal Modal--large';
  }

  title() {
    return this.attrs.title;
  }

  onremove(vnode: Mithril.VnodeDOM<GenerateModalAttrs>) {
    super.onremove(vnode);
    this.removeBeforeUnload();
  }

  addBeforeUnload() {
    this.beforeUnloadHandler = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', this.beforeUnloadHandler);
  }

  removeBeforeUnload() {
    if (this.beforeUnloadHandler) {
      window.removeEventListener('beforeunload', this.beforeUnloadHandler);
      this.beforeUnloadHandler = null;
    }
  }

  validate(): string | null {
    const attrs = this.attrs;
    if (!attrs.configFields) return null;

    for (const field of attrs.configFields) {
      if (field.type === 'number' && field.key === 'year') {
        const val = this.config[field.key];
        const maxYear = getActiveYear();
        if (!val || !Number.isInteger(val) || val < 2000 || val > maxYear) {
          return app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.invalid_year') as string;
        }
      }
    }

    return null;
  }

  async start() {
    const error = this.validate();
    if (error) {
      this.failed = true;
      this.errorMessage = error;
      this.phase = 'done';
      m.redraw();
      return;
    }

    const attrs = this.attrs;

    // Confirm mode (for delete)
    if (attrs.confirmMode && attrs.onConfirm) {
      this.phase = 'running';
      this.addBeforeUnload();
      m.redraw();

      try {
        this.deleteCount = await attrs.onConfirm(this.config);
        this.phase = 'done';
      } catch (e: any) {
        this.failed = true;
        this.errorMessage = e?.message || 'An error occurred';
        this.phase = 'done';
      } finally {
        this.removeBeforeUnload();
        m.redraw();
      }
      return;
    }

    // Step mode (for generate)
    this.phase = 'running';
    this.addBeforeUnload();
    m.redraw();

    try {
      this.steps = await attrs.loadSteps(this.config);

      if (this.steps.length === 0) {
        this.phase = 'done';
        this.removeBeforeUnload();
        m.redraw();
        return;
      }

      for (let i = 0; i < this.steps.length; i++) {
        this.currentIndex = i;
        m.redraw();
        await attrs.executeStep(this.steps[i], this.config);
      }

      this.currentIndex = this.steps.length;
      this.phase = 'done';
    } catch (e: any) {
      this.failed = true;
      this.errorMessage = e?.message || 'An error occurred';
      this.phase = 'done';
    } finally {
      this.removeBeforeUnload();
      m.redraw();
      if (!this.failed && attrs.onComplete) {
        attrs.onComplete();
      }
    }
  }

  content() {
    const attrs = this.attrs;

    if (this.phase === 'loading') {
      return (
        <div className="Modal-body GenerateModal-body">
          <div className="GenerateModal-loading">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (this.phase === 'config') {
      return this.renderConfig(attrs);
    }

    return this.renderProgress(attrs);
  }

  renderConfig(attrs: GenerateModalAttrs): Mithril.Children {
    const fields = this.resolvedFields;

    return (
      <div className="Modal-body GenerateModal-body">
        <div className="GenerateModal-config">
          {fields.map((field) => (
            <div className="GenerateModal-field" key={field.key}>
              <label>{field.label}</label>
              {field.type === 'number' && (
                <input
                  className="FormControl"
                  type="number"
                  value={this.config[field.key]}
                  onchange={(e: Event) => {
                    this.config[field.key] = parseInt((e.target as HTMLInputElement).value, 10);
                  }}
                />
              )}
              {field.type === 'select' && (
                <select
                  className="FormControl"
                  value={this.config[field.key]}
                  onchange={(e: Event) => {
                    const val = (e.target as HTMLSelectElement).value;
                    this.config[field.key] = val === '' ? null : val;
                  }}
                >
                  {(field.options || []).map((opt) => (
                    <option key={opt.value} value={opt.value ?? ''}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              )}
            </div>
          ))}
        </div>
        <div className="GenerateModal-actions">
          <Button className="Button" onclick={() => this.hide()}>
            {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.cancel')}
          </Button>
          <Button className="Button Button--primary" onclick={() => this.start()}>
            {attrs.confirmMode
              ? attrs.confirmLabel || app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.confirm')
              : app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.start')}
          </Button>
        </div>
      </div>
    );
  }

  renderProgress(attrs: GenerateModalAttrs): Mithril.Children {
    const total = this.steps.length;
    const current = this.currentIndex;
    const progress = total > 0 ? Math.round((current / total) * 100) : 0;
    const currentStep = current >= 0 && current < total ? this.steps[current] : null;
    const isDone = this.phase === 'done';
    const isConfirmMode = attrs.confirmMode;

    return (
      <div className="Modal-body GenerateModal-body">
        {this.phase === 'running' && (
          <div className="GenerateModal-warning">
            <i className="fas fa-exclamation-triangle" />
            {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.warning')}
          </div>
        )}

        {!isConfirmMode && (
          <div className="GenerateModal-progress">
            <div className="GenerateModal-progressBar">
              <div
                className={
                  'GenerateModal-progressFill' +
                  (isDone && !this.failed ? ' GenerateModal-progressFill--done' : '') +
                  (this.failed ? ' GenerateModal-progressFill--error' : '')
                }
                style={{ width: `${isDone && !this.failed ? 100 : progress}%` }}
              />
            </div>
            <div className="GenerateModal-progressInfo">
              {this.phase === 'running' && currentStep && (
                <span className="GenerateModal-stepLabel">
                  {current + 1}/{total} — {currentStep.label}
                </span>
              )}
              {isDone && !this.failed && total > 0 && (
                <span className="GenerateModal-doneLabel">
                  <i className="fas fa-check-circle" /> {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.done', { count: total })}
                </span>
              )}
              {isDone && !this.failed && total === 0 && (
                <span className="GenerateModal-doneLabel">
                  <i className="fas fa-check-circle" /> {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.nothing')}
                </span>
              )}
            </div>
          </div>
        )}

        {isConfirmMode && isDone && !this.failed && (
          <div className="GenerateModal-progressInfo">
            <span className="GenerateModal-doneLabel">
              <i className="fas fa-check-circle" />{' '}
              {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.deleted', { count: this.deleteCount })}
            </span>
          </div>
        )}

        {this.failed && (
          <div className="GenerateModal-progressInfo">
            <span className="GenerateModal-errorLabel">
              <i className="fas fa-times-circle" /> {this.errorMessage}
            </span>
          </div>
        )}

        <div className="GenerateModal-actions">
          {isDone && (
            <Button className="Button Button--primary" onclick={() => this.hide()}>
              {app.translator.trans('huseyinfiliz-rewind.admin.generate_modal.close')}
            </Button>
          )}
        </div>
      </div>
    );
  }
}
