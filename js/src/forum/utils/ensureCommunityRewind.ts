import app from 'flarum/forum/app';

export default async function ensureCommunityRewind(year: number, cancelled: () => boolean): Promise<boolean> {
  if (
    !app.session.user ||
    !app.session.user.attribute('isEmailConfirmed') ||
    !app.forum.attribute('rewindEnabled') ||
    !app.forum.attribute('canViewRewind') ||
    !app.forum.attribute('rewindAutoCommunity') ||
    year !== app.forum.attribute<number>('rewindActiveYear') ||
    year > app.forum.attribute<number>('rewindPreviousYear')
  ) {
    return false;
  }

  while (!cancelled()) {
    const progress = await app.request<{ ready: boolean; busy?: boolean }>({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/rw-community/ensure',
      body: { year },
    });
    if (progress.ready) return !cancelled();
    await new Promise((resolve) => setTimeout(resolve, progress.busy ? 1500 : 250));
  }

  return false;
}
