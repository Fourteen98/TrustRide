import { Pressable, StyleSheet, TextInput, View, type TextInputProps } from 'react-native';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { Icon } from './icon';
import { ThemedText } from './themed-text';

const shadow = { shadowColor: '#000', shadowOpacity: 0.12, shadowRadius: 10, shadowOffset: { width: 0, height: 3 }, elevation: 6 } as const;

/**
 * The "Where to?" box: a rounded pill with a magnifying glass. With `onPress` it is a button that opens
 * the search screen. Without it, it is the box she types in.
 */
export function SearchField({ onPress, placeholder = 'Where to?', ...input }: TextInputProps & { onPress?: () => void }) {
  const theme = useTheme();
  const glass = <Icon name="magnifyingglass" glyph="⌕" color={theme.text} size={20} />;

  if (onPress) {
    return (
      <Pressable
        accessibilityRole="search"
        accessibilityLabel={placeholder}
        onPress={onPress}
        style={({ pressed }) => [styles.pill, shadow, { backgroundColor: theme.background, opacity: pressed ? 0.85 : 1 }]}>
        {glass}
        <ThemedText style={styles.text} themeColor="textSecondary">
          {placeholder}
        </ThemedText>
      </Pressable>
    );
  }

  return (
    <View style={[styles.pill, { backgroundColor: theme.backgroundElement }]}>
      {glass}
      <TextInput
        accessibilityLabel={placeholder}
        placeholder={placeholder}
        placeholderTextColor={theme.textSecondary}
        returnKeyType="search"
        autoCorrect={false}
        clearButtonMode="while-editing"
        style={[styles.text, styles.input, { color: theme.text }]}
        {...input}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  pill: {
    flex: 1,
    height: 52,
    borderRadius: 26,
    paddingHorizontal: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    gap: Spacing.two,
  },
  text: { fontSize: 18, fontWeight: 600 },
  input: { flex: 1, height: '100%' },
});
