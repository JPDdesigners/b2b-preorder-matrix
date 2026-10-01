export function displayColorName(value) {
  const name = String(value ?? '');
  if (!name || name !== name.toLocaleUpperCase() || name === name.toLocaleLowerCase()) return name;
  return name.toLocaleLowerCase().replace(/(^|[\s/\-])(\p{L})/gu, (_, separator, letter) => separator + letter.toLocaleUpperCase());
}
