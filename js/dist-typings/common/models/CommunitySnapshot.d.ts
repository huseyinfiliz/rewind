import Model from 'flarum/common/Model';
export default class CommunitySnapshot extends Model {
    year: () => number;
    snapshotData: () => Record<string, any>;
    generatedAt: () => Date | null | undefined;
}
