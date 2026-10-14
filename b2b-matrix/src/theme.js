export const THEME_KEY = Symbol('b2b-matrix-theme');
export const SYSTEM_FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
export const PALETTES = {
  indigo: {
    primary: '#4f46e5',
    hover: '#4338ca',
    soft: '#eef2ff',
    border: '#c7d2fe',
    onPrimary: '#ffffff',
    focusRing: '#4f46e5',
    primaryInk: '#4f46e5',
  },
  blue: {
    primary: '#2563eb',
    hover: '#1d4ed8',
    soft: '#eff6ff',
    border: '#bfdbfe',
    onPrimary: '#ffffff',
    focusRing: '#2563eb',
    primaryInk: '#2563eb',
  },
  violet: {
    primary: '#7c3aed',
    hover: '#6d28d9',
    soft: '#f5f3ff',
    border: '#ddd6fe',
    onPrimary: '#ffffff',
    focusRing: '#7c3aed',
    primaryInk: '#7c3aed',
  },
  teal: {
    primary: '#0f766e',
    hover: '#115e59',
    soft: '#f0fdfa',
    border: '#99f6e4',
    onPrimary: '#ffffff',
    focusRing: '#0f766e',
    primaryInk: '#0f766e',
  },
  slate: {
    primary: '#334155',
    hover: '#1e293b',
    soft: '#f1f5f9',
    border: '#cbd5e1',
    onPrimary: '#ffffff',
    focusRing: '#334155',
    primaryInk: '#334155',
  },
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
  return (
    '#' +
    [r, g, b]
      .map(x => Math.max(0, Math.min(255, Math.round(x))).toString(16).padStart(2, '0'))
      .join('')
  );
}

function mixRgb(rgb1, rgb2, weight) {
  return rgb1.map((c, i) => c * (1 - weight) + rgb2[i] * weight);
}

export function relativeLuminance(hex) {
  const [r, g, b] = hexToRgb(hex).map(channel => {
    const value = channel / 255;
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function generatePalette(hex) {
  const rgb = hexToRgb(hex);
  const primary = rgbToHex(...rgb);
  const lum = relativeLuminance(primary);
  const isLight = 1.05 / (lum + 0.05) < 4.5;
  const hover = rgbToHex(...mixRgb(rgb, [0, 0, 0], lum > 0.75 ? 0.16 : 0.12));
  const soft = lum > 0.85 ? '#f1f5f9' : rgbToHex(...mixRgb(rgb, [255, 255, 255], 0.92));
  const border =
    lum > 0.6
      ? rgbToHex(...mixRgb(rgb, [100, 116, 139], 0.45))
      : rgbToHex(...mixRgb(rgb, [255, 255, 255], 0.72));
  const onPrimary = isLight ? '#0f172a' : '#ffffff';
  const focusRing = lum > 0.3 ? '#334155' : primary;
  const primaryInk = lum > 0.3 ? rgbToHex(...mixRgb(rgb, [15, 23, 42], 0.72)) : primary;
  return { primary, hover, soft, border, onPrimary, focusRing, primaryInk };
}

export function normalizeAppearance(value = {}) {
  value = value && typeof value === 'object' ? value : {};
  const isCustom =
    value.palette === 'custom' &&
    typeof value.custom_color === 'string' &&
    /^#[0-9a-fA-F]{6}$/.test(value.custom_color);
  const result = {
    palette: isCustom ? 'custom' : Object.hasOwn(PALETTES, value.palette) ? value.palette : 'indigo',
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
  const palette =
    settings.palette === 'custom' && settings.custom_color
      ? generatePalette(settings.custom_color)
      : PALETTES[settings.palette];
  const square = settings.radius === 'square';
  return {
    '--b2b-primary': palette.primary,
    '--b2b-primary-hover': palette.hover,
    '--b2b-primary-soft': palette.soft,
    '--b2b-primary-border': palette.border,
    '--b2b-on-primary': palette.onPrimary,
    '--b2b-focus-ring': palette.focusRing,
    '--b2b-primary-ink': palette.primaryInk,
    '--b2b-font': settings.inherit_font ? inheritedFont : SYSTEM_FONT,
    '--b2b-radius-lg': square ? '0px' : '14px',
    '--b2b-radius': square ? '0px' : '7px',
    '--b2b-radius-input': square ? '0px' : '8px',
    '--b2b-radius-sm': square ? '0px' : '4px',
    '--b2b-radius-mobile': square ? '0px' : '10px',
    '--b2b-radius-image': square ? '0px' : '6px',
    '--b2b-radius-pill': square ? '0px' : '999px',
  };
}
