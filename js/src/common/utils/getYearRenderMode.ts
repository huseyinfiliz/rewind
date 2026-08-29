import app from 'flarum/common/app';

export function getYearRenderMode(year?: number | string | null): 'slideshow' | 'blade' {
  if (!year) return 'slideshow';

  try {
    const raw = app.forum.attribute<string>('rewindYearRenderModes') || '{}';
    const map = typeof raw === 'string' ? JSON.parse(raw) : raw;
    if (map && typeof map === 'object' && map[String(year)]) {
      return map[String(year)] as 'slideshow' | 'blade';
    }
  } catch {
    // fallback
  }

  return 'slideshow';
}
