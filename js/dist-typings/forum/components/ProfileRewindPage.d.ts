import UserPage from 'flarum/forum/components/UserPage';
import type Mithril from 'mithril';
import type User from 'flarum/common/models/User';
export default class ProfileRewindPage extends UserPage {
    loading: boolean;
    generating: boolean;
    snapshots: any[];
    oninit(vnode: Mithril.Vnode): void;
    show(user: User): void;
    isOwner(): boolean;
    loadSnapshots(user: User): Promise<void>;
    openSlideshow(snapshot: any): void;
    generateAndOpen(): Promise<void>;
    savingPublic: boolean;
    togglePublic(snapshot: any): Promise<void>;
    regenerateSnapshot(snapshot: any): Promise<void>;
    deleteSnapshot(snapshot: any): Promise<void>;
    content(): Mithril.Children;
    renderGrid(): Mithril.Children;
}
