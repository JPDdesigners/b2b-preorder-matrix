export const THEME_KEY = Symbol('b2b-matrix-theme');
export const SYSTEM_FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
export const PALETTES = {
  indigo: { primary: '#4f46e5', hover: '#4338ca', soft: '#eef2ff', border: '#c7d2fe' },
  blue: { primary: '#2563eb', hover: '#1d4ed8', soft: '#eff6ff', border: '#bfdbfe' },
  violet: { primary: '#7c3aed', hover: '#6d28d9', soft: '#f5f3ff', border: '#ddd6fe' },
  teal: { primary: '#0f766e', hover: '#115e59', soft: '#f0fdfa', border: '#99f6e4' },
  slate: { primary: '#334155', hover: '#1e293b', soft: '#f1f5f9', border: '#cbd5e1' },
};

function hexToRgb(hex) {
  const clean = String(hex || '').replace('#', '');
  if (!/^[0-9a-fA-F]{6}$/.test(clean)) return [79, 70, 229];
  return [
    parseInt(clean.slice(0, 2), 16),
    parseInt(clean.slice(2, 4), 16),
    parseInt(clean.slice(4, 6), 16),
  ];
}

function rgbToHex(r, g, b) {
  return '#' + [r, g, b].map(x => Math.max(0, Math.min(255, Math.round(x))).toString(16).padStart(2, '0')).join('');
}

function mixRgb(rgb1, rgb2, weight) {
  return rgb1.map((c, i) => c * (1 - weight) + rgb2[i] * weight);
}

export function generatePalette(hex) {
  const rgb = hexToRgb(hex);
  const primary = rgbToHex(...rgb);
  const hover = rgbToHex(...mixRgb(rgb, [0, 0, 0], 0.12));
  const soft = rgbToHex(...mixRgb(rgb, [255, 255, 255], 0.92));
  const border = rgbToHex(...mixRgb(rgb, [255, 255, 255], 0.72));
  return { primary, hover, soft, border };
}

export function normalizeAppearance(value = {}) {
  value = value && typeof value === 'object' ? value : {};
  const isCustom = value.palette === 'custom' && typeof value.custom_color === 'string' && /^#[0-9a-fA-F]{6}$/.test(value.custom_color);
  const result = {
    palette: isCustom ? 'custom' : (Object.hasOwn(PALETTES, value.palette) ? value.palette : 'indigo'),
    inherit_font: ![false, 0, '0'].includes(value.inherit_font),
    radius: value.radius === 'square' ? 'square' : 'rounded',
  };
  if (isCustom) {
    result.custom_color = value.custom_color.toLowerCase();
  }
  return result;
}

export function appearanceStyle(value, inheritedFont = SYSTEM_FONT) {
  const settings = normalizeAppearance(value);
  const palette = settings.palette === 'custom' && settings.custom_color
    ? generatePalette(settings.custom_color)
    : PALETTES[settings.palette];
  const square = settings.radius === 'square';
  return {
    '--b2b-primary': palette.primary, '--b2b-primary-hover': palette.hover,
    '--b2b-primary-soft': palette.soft, '--b2b-primary-border': palette.border,
    '--b2b-font': settings.inherit_font ? inheritedFont : SYSTEM_FONT,
    '--b2b-radius-lg': square ? '0px' : '14px', '--b2b-radius': square ? '0px' : '7px',
    '--b2b-radius-input': square ? '0px' : '8px', '--b2b-radius-sm': square ? '0px' : '4px',
    '--b2b-radius-mobile': square ? '0px' : '10px', '--b2b-radius-image': square ? '0px' : '6px',
    '--b2b-radius-pill': square ? '0px' : '999px',
  };
}
