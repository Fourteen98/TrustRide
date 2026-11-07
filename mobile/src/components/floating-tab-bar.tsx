import type { BottomTabBarProps } from 'expo-router/tabs';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { ThemedText } from './themed-text';

/** The height of the bar itself, without the space under it. */
export const TAB_BAR_HEIGHT = 64;

/** The room the bar takes from the bottom of the screen, given the phone's own bottom inset. */
export function tabBarSpace(bottomInset: number): number {
  return TAB_BAR_HEIGHT + Math.max(bottomInset, Spacing.three) + Spacing.two;
}

/**
 * The tabs as a pill floating over the bottom of the screen, so a map runs underneath it.
 * The chosen tab sits in its own shaded capsule. Tabs hidden with `href: null` are left out.
 */
export function FloatingTabBar({ state, descriptors, navigation }: BottomTabBarProps) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();

  return (
    <View pointerEvents="box-none" style={[styles.wrap, { bottom: Math.max(insets.bottom, Spacing.three) }]}>
      <View style={[styles.bar, { backgroundColor: theme.background, borderColor: theme.border }]}>
        {state.routes.map((route, index) => {
          const { options } = descriptors[route.key];
          // expo-router hides a tab given `href: null` by setting tabBarItemStyle to display none.
          const hidden = (options.tabBarItemStyle as { display?: string } | undefined)?.display === 'none';

          if (hidden) {
            return null;
          }

          const focused = state.index === index;
          const label = typeof options.title === 'string' ? options.title : route.name;
          const color = focused ? theme.text : theme.textSecondary;

          const press = () => {
            const event = navigation.emit({ type: 'tabPress', target: route.key, canPreventDefault: true });

            if (!focused && !event.defaultPrevented) {
              navigation.navigate(route.name, route.params);
            }
          };

          return (
            <Pressable
              key={route.key}
              accessibilityRole="tab"
              accessibilityState={{ selected: focused }}
              accessibilityLabel={label}
              onPress={press}
              style={[styles.tab, focused && { backgroundColor: theme.backgroundElement }]}>
              {options.tabBarIcon?.({ focused, color, size: 22 })}
              <ThemedText type="small" style={[styles.label, { color }, focused && styles.focused]}>
                {label}
              </ThemedText>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { position: 'absolute', left: Spacing.three, right: Spacing.three, alignItems: 'center' },
  bar: {
    height: TAB_BAR_HEIGHT,
    width: '100%',
    maxWidth: 480,
    borderRadius: TAB_BAR_HEIGHT / 2,
    borderWidth: StyleSheet.hairlineWidth,
    flexDirection: 'row',
    alignItems: 'center',
    padding: Spacing.one,
    shadowColor: '#000',
    shadowOpacity: 0.15,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 12,
  },
  tab: {
    flex: 1,
    height: '100%',
    borderRadius: (TAB_BAR_HEIGHT - Spacing.two) / 2,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 2,
  },
  label: { fontSize: 12, lineHeight: 16 },
  focused: { fontWeight: 700 },
});
