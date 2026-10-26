import app from 'flarum/admin/app';

/** The effective year always comes from the server, never the browser clock. */
export default function activeYear(): number {
  const previous = app.forum.attribute<number>('rewindPreviousYear');
  const automatic = app.data.settings['huseyinfiliz-rewind.automatic_year'];
  return automatic && automatic !== '0' ? previous : Number(app.data.settings['huseyinfiliz-rewind.active_year']) || previous;
}
