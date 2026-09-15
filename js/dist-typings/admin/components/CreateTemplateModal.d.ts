import FormModal, { type IFormModalAttrs } from 'flarum/common/components/FormModal';
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
    type: 'user' | 'community' | 'error';
    scope: 'year' | 'default';
    year: number;
    errorMessage: string;
    oninit(vnode: Mithril.Vnode<CreateTemplateModalAttrs>): void;
    className(): string;
    title(): Mithril.Children;
    getTargetId(): string;
    getTargetFilename(): string;
    alreadyExists(): boolean;
    isValid(): boolean;
    content(): Mithril.Children;
    onsubmit(e: SubmitEvent): void;
}
