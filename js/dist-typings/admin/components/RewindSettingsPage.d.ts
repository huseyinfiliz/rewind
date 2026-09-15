import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { SaveSubmitEvent } from 'flarum/admin/components/AdminPage';
import type Mithril from 'mithril';
type SlideSection = {
    key: string;
    icon: string;
    label: string;
    slides: string[];
};
export default class RewindSettingsPage extends ExtensionPage {
    private activeTab;
    private slidesSubTab;
    private expandedSections;
    private historicalYears;
    oninit(vnode: Mithril.Vnode<any, any>): void;
    loadHistoricalYears(): Promise<void>;
    saveSettings(e: SaveSubmitEvent): Promise<void>;
    content(): JSX.Element;
    tabButton(tab: string, iconClass: string, labelKey: string): Mithril.Children;
    generalTab(): Mithril.Children;
    slidesTab(): Mithril.Children;
    renderSlideToggles(sections: SlideSection[], settingKey: string, labelPrefix: string): Mithril.Children;
    toggleSection(key: string): void;
    advancedTab(): Mithril.Children;
    loadGroupOptions(): Promise<Array<{
        value: any;
        label: string;
    }>>;
    loadYearOptions(): Promise<Array<{
        value: any;
        label: string;
    }>>;
    openCommunityGenerateModal(): void;
    openUserBatchGenerateModal(): void;
    openDeleteRewindsModal(): void;
    templatesTab(): Mithril.Children;
    renderVarRow(variable: string, desc: string): Mithril.Children;
    copyToClipboard(text: string): void;
    getYearModes(): Record<string, string>;
    setYearMode(year: string | number, mode: string): void;
    getAllDisplayYears(): number[];
}
export {};
