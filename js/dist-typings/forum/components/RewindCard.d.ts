import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
interface RewindCardAttrs {
    metricKey: string;
    data: Record<string, any>;
    index?: number;
}
export default class RewindCard extends Component<RewindCardAttrs> {
    view(): Mithril.Children;
    oncreate(vnode: Mithril.VnodeDOM): void;
    renderMetric(key: string, data: Record<string, any>): Mithril.Children;
}
export {};
