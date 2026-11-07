import { SymbolView, type SFSymbol } from 'expo-symbols';
import { Text, type ColorValue } from 'react-native';

/**
 * A small icon. iPhone draws Apple's own symbol. Android and the web have no such symbols, so they show
 * the plain character given as `glyph`, which keeps the layout the same.
 */
export function Icon({ name, glyph, color, size = 22 }: { name: SFSymbol; glyph: string; color: ColorValue; size?: number }) {
  return (
    <SymbolView
      name={name}
      tintColor={color}
      size={size}
      weight="semibold"
      fallback={<Text style={{ color, fontSize: size * 0.8, lineHeight: size, textAlign: 'center', width: size }}>{glyph}</Text>}
    />
  );
}
