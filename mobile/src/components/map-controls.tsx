import { useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { Pressable, StyleSheet, View } from 'react-native';

import { useAuth } from '@/auth/auth-context';
import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { SearchField } from './search-field';
import { ThemedText } from './themed-text';

/**
 * A pill floating on the map: a few choices side by side, the chosen one filled. For switching between today's runs.
 * A `muted` choice, such as a run already done, is greyed but can still be chosen.
 */
export function MapChoices<T extends string | number>({
  options,
  value,
  onChange,
}: {
  options: { value: T; label: string; muted?: boolean }[];
  value: T;
  onChange: (value: T) => void;
}) {
  const theme = useTheme();

  return (
    <View style={[styles.choices, { backgroundColor: theme.background }]} accessibilityRole="tablist">
      {options.map((option) => {
        const selected = option.value === value;

        return (
          <Pressable
            key={String(option.value)}
            accessibilityRole="tab"
            accessibilityState={{ selected }}
            onPress={() => onChange(option.value)}
            style={[styles.choice, selected && { backgroundColor: theme.primary }]}>
            <ThemedText
              type="smallBold"
              numberOfLines={1}
              style={{ color: selected ? theme.onPrimary : option.muted ? theme.textSecondary : theme.text }}>
              {option.label}
            </ThemedText>
          </Pressable>
        );
      })}
    </View>
  );
}

/** A round button floating on the map. */
export function MapButton({
  label,
  text,
  onPress,
  tone = 'plain',
}: {
  label: string;
  text: string;
  onPress: () => void;
  tone?: 'plain' | 'strong';
}) {
  const theme = useTheme();
  const strong = tone === 'strong';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      hitSlop={8}
      onPress={onPress}
      style={({ pressed }) => [styles.round, { backgroundColor: strong ? theme.primary : theme.background, opacity: pressed ? 0.8 : 1 }]}>
      <ThemedText type="smallBold" style={{ color: strong ? theme.onPrimary : theme.text }}>
        {text}
      </ThemedText>
    </Pressable>
  );
}

/**
 * The bar floating at the top of a map screen, as in a ride app: the "Where to?" box, and Help beside it.
 * A screen can put its own control in place of the box, as the crew's run switch does.
 */
export function MapTopBar({ children }: { children?: ReactNode }) {
  const router = useRouter();
  const { can } = useAuth();

  return (
    <View style={styles.bar} pointerEvents="box-none">
      {children ? (
        <View style={styles.grow}>{children}</View>
      ) : can('riders.profile.manage') ? (
        <SearchField onPress={() => router.push({ pathname: '/routes', params: { focus: '1' } })} />
      ) : (
        <View style={styles.grow} />
      )}
      {can('support.alert.raise') ? <MapButton label="Help" text="Help" onPress={() => router.push('/help')} /> : null}
    </View>
  );
}

/** The one line at the top of the sheet that says what matters now, with a gold dot while her van is live. */
export function SheetStatus({ text, live = false }: { text: string; live?: boolean }) {
  const theme = useTheme();

  return (
    <View style={styles.status}>
      {live ? <View style={[styles.liveDot, { backgroundColor: theme.primary }]} /> : null}
      <ThemedText type="subtitle" numberOfLines={2} style={styles.grow}>
        {text}
      </ThemedText>
    </View>
  );
}

const shadow = {
  shadowColor: '#000',
  shadowOpacity: 0.18,
  shadowRadius: 8,
  shadowOffset: { width: 0, height: 2 },
  elevation: 6,
} as const;

const styles = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  round: {
    minWidth: 52,
    height: 52,
    borderRadius: 26,
    paddingHorizontal: Spacing.two,
    alignItems: 'center',
    justifyContent: 'center',
    ...shadow,
  },
  grow: { flex: 1 },
  choices: { height: 52, borderRadius: 26, padding: 4, flexDirection: 'row', gap: 4, alignSelf: 'flex-start', ...shadow },
  choice: { borderRadius: 22, paddingHorizontal: Spacing.three, justifyContent: 'center' },
  status: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two },
  liveDot: { width: 8, height: 8, borderRadius: 4 },
});
