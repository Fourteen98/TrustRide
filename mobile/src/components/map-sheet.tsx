import type { ReactNode } from 'react';
import { RefreshControl, StyleSheet, useWindowDimensions, View } from 'react-native';
import { Gesture, GestureDetector } from 'react-native-gesture-handler';
import Animated, { useAnimatedStyle, useSharedValue, withSpring } from 'react-native-reanimated';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { MaxContentWidth, Spacing } from '@/constants/theme';
import { useTheme } from '@/hooks/use-theme';

import { useTabBarSpace } from './tab-bar-space';

type Props = {
  /** The map, filling the whole screen behind everything. */
  map: ReactNode;
  /** Laid over the top of the map: the floating bar. */
  top?: ReactNode;
  /** Laid over the map just above the sheet: round map buttons. */
  float?: ReactNode;
  /** The start of the sheet, always in view. The sheet is dragged by this and by the handle. */
  head?: ReactNode;
  /** The rest of the sheet. It scrolls once the sheet is pulled up. */
  children: ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
};

/** How much of the screen the sheet covers when down, and when pulled up. */
export const SHEET_DOWN = 0.46;
const SHEET_UP = 0.88;
const SHEET_RADIUS = 24;
const SPRING = { damping: 22, stiffness: 220, mass: 0.9 };

/**
 * A screen that is a map with a sheet over it: the map behind everything, a bar floating at the top,
 * and a sheet at the bottom that rests low so the map shows, and pulls up to read the rest.
 * Drag it by its handle or its head. Tap the handle to switch between low and high.
 */
export function MapSheet({ map, top, float, head, children, refreshing = false, onRefresh }: Props) {
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { height } = useWindowDimensions();
  // Inside the tabs, the floating tab bar covers the bottom of the sheet, so its content ends above it.
  const tabBar = useTabBarSpace();

  const up = height * SHEET_UP;
  const down = height * SHEET_DOWN;
  // The sheet is always `up` tall; it is pushed down by `offset` to rest low.
  const lowest = up - down;
  const offset = useSharedValue(lowest);
  const start = useSharedValue(lowest);

  const drag = Gesture.Pan()
    .onBegin(() => {
      start.value = offset.value;
    })
    .onUpdate((event) => {
      offset.value = Math.min(lowest + 40, Math.max(-20, start.value + event.translationY));
    })
    .onEnd((event) => {
      const goingUp = event.velocityY < -400 || (event.velocityY < 400 && offset.value < lowest / 2);
      offset.value = withSpring(goingUp ? 0 : lowest, SPRING);
    });

  const tap = Gesture.Tap().onEnd(() => {
    offset.value = withSpring(offset.value > lowest / 2 ? 0 : lowest, SPRING);
  });

  const sheetStyle = useAnimatedStyle(() => ({ transform: [{ translateY: offset.value }] }));
  // The map buttons ride on top of the sheet, and fade away as it is pulled up past the map, so they never sit on
  // the bar at the top. Once gone they are moved off the screen too, so they can't be tapped unseen.
  const floatStyle = useAnimatedStyle(() => {
    const shown = Math.min(1, Math.max(0, offset.value / (lowest * 0.5)));

    return { opacity: shown, transform: [{ translateY: shown === 0 ? 10000 : offset.value }] };
  });

  return (
    <View style={styles.fill}>
      {/* The map ends where the resting sheet begins, tucked under its rounded corners, so nothing is drawn behind the sheet. */}
      <View style={[styles.map, { bottom: down - SHEET_RADIUS }]}>{map}</View>

      <View pointerEvents="box-none" style={[styles.top, { paddingTop: insets.top + Spacing.two }]}>
        {top}
      </View>

      <Animated.View pointerEvents="box-none" style={[styles.float, { bottom: up + Spacing.three }, floatStyle]}>
        {float}
      </Animated.View>

      <Animated.View style={[styles.sheet, { height: up, backgroundColor: theme.background }, sheetStyle]}>
        <GestureDetector gesture={Gesture.Exclusive(drag, tap)}>
          <View style={styles.grab}>
            <View style={[styles.handle, { backgroundColor: theme.border }]} />
            {head ? <View style={styles.head}>{head}</View> : null}
          </View>
        </GestureDetector>
        <Animated.ScrollView
          contentContainerStyle={[styles.content, { paddingBottom: Math.max(insets.bottom, tabBar) + Spacing.four + lowest }]}
          refreshControl={
            onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={theme.primary} /> : undefined
          }>
          {children}
        </Animated.ScrollView>
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  map: { position: 'absolute', top: 0, left: 0, right: 0 },
  top: { position: 'absolute', top: 0, left: 0, right: 0, paddingHorizontal: Spacing.three },
  float: {
    position: 'absolute',
    right: Spacing.three,
    left: Spacing.three,
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: Spacing.two,
  },
  sheet: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    borderTopLeftRadius: SHEET_RADIUS,
    borderTopRightRadius: SHEET_RADIUS,
    shadowColor: '#000',
    shadowOpacity: 0.15,
    shadowRadius: 16,
    shadowOffset: { width: 0, height: -4 },
    elevation: 16,
  },
  grab: { paddingTop: Spacing.two, paddingHorizontal: Spacing.four, gap: Spacing.three },
  handle: { alignSelf: 'center', width: 44, height: 5, borderRadius: 3 },
  head: { width: '100%', maxWidth: MaxContentWidth, alignSelf: 'center' },
  content: {
    padding: Spacing.four,
    paddingTop: Spacing.three,
    gap: Spacing.three,
    width: '100%',
    maxWidth: MaxContentWidth,
    alignSelf: 'center',
  },
});
