import Model from 'flarum/common/Model';
import User from 'flarum/common/models/User';
export default class RewindSnapshot extends Model {
    year: () => number;
    snapshotData: () => Record<string, any>;
    generatedAt: () => Date | null | undefined;
    isPublic: () => boolean;
    canEdit: () => boolean;
    canModerate: () => boolean;
    isEmpty: () => boolean;
    user: () => false | User;
}
