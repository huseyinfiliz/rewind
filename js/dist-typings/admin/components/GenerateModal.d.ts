import Modal, { type IInternalModalAttrs } from 'flarum/common/components/Modal';
import type Mithril from 'mithril';
export type GenerateStep = {
    key: string;
    label: string;
};
export type ConfigField = {
    type: 'number' | 'select';
    key: string;
    label: string;
    value: any;
    options?: Array<{
        value: any;
        label: string;
    }>;
    loadOptions?: () => Promise<Array<{
        value: any;
        label: string;
    }>>;
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
    phase: Phase;
    steps: GenerateStep[];
    currentIndex: number;
    failed: boolean;
    errorMessage: string;
    config: Record<string, any>;
    resolvedFields: ConfigField[];
    deleteCount: number;
    private beforeUnloadHandler;
    oninit(vnode: Mithril.Vnode<GenerateModalAttrs>): void;
    loadConfigOptions(): Promise<void>;
    className(): string;
    title(): string;
    onremove(vnode: Mithril.VnodeDOM<GenerateModalAttrs>): void;
    addBeforeUnload(): void;
    removeBeforeUnload(): void;
    validate(): string | null;
    start(): Promise<void>;
    content(): JSX.Element | Mithril.Children;
    renderConfig(attrs: GenerateModalAttrs): Mithril.Children;
    renderProgress(attrs: GenerateModalAttrs): Mithril.Children;
}
export {};
