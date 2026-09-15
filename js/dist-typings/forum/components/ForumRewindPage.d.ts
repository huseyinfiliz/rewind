import Page from 'flarum/common/components/Page';
import type Mithril from 'mithril';
export default class ForumRewindPage extends Page {
    loading: boolean;
    loadingContent: boolean;
    generating: boolean;
    communitySnapshot: any;
    userHasSnapshot: boolean;
    selectedYear: number;
    availableYears: number[];
    oninit(vnode: Mithril.Vnode): void;
    loadAvailableYears(): Promise<void>;
    loadData(initial?: boolean): Promise<void>;
    switchYear(year: number): void;
    confirmRegenerate(): void;
    generateCommunity(): Promise<void>;
    view(): Mithril.Children;
    renderHero(enabled: boolean, canModerate: boolean): Mithril.Children;
    renderToolbar(year: number, user: any): Mithril.Children;
    renderNoCommunity(): Mithril.Children;
    renderCommunityStats(): Mithril.Children;
    renderStatCard(icon: string, value: string | number, label: string, isText?: boolean): Mithril.Children;
    renderWideCard(icon: string, label: string, title: string, subtitle?: string): Mithril.Children;
}
