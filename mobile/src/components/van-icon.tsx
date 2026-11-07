import { StyleSheet, View } from 'react-native';

export type VanTone = 'ours' | 'other' | 'stale';

const BODY: Record<VanTone, string> = { ours: '#4DB6E8', other: '#5F6368', stale: '#9AA0A6' };
const GLASS = '#14324A';

/**
 * A Aveo van seen from above, nose up, turned to face the way it drives. No number plate: on the map a van is a
 * van. "ours" is the van that matters to whoever is looking (her van, or his own), "other" is another Aveo van,
 * "stale" is a van whose phones have gone quiet.
 */
export function VanIcon({ tone = 'ours', heading = null, size = 1 }: { tone?: VanTone; heading?: number | null; size?: number }) {
  return (
    <View style={[styles.wrap, { transform: [{ rotate: `${Math.round(heading ?? 0)}deg` }, { scale: size }] }]}>
      <View style={[styles.body, { backgroundColor: BODY[tone] }]}>
        <View style={styles.windscreen} />
        <View style={styles.roof}>
          <View style={styles.rail} />
          <View style={styles.rail} />
        </View>
        <View style={styles.rear} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  // Square, so turning it never clips the corners.
  wrap: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  body: {
    width: 20,
    height: 38,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: '#fff',
    alignItems: 'center',
    paddingVertical: 3,
    justifyContent: 'space-between',
    shadowColor: '#000',
    shadowOpacity: 0.3,
    shadowRadius: 3,
    shadowOffset: { width: 0, height: 1 },
    elevation: 4,
  },
  windscreen: {
    width: 13,
    height: 7,
    borderTopLeftRadius: 4,
    borderTopRightRadius: 4,
    borderBottomLeftRadius: 1,
    borderBottomRightRadius: 1,
    backgroundColor: GLASS,
  },
  roof: { flex: 1, width: 10, flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 3 },
  rail: { width: 1.5, backgroundColor: 'rgba(255,255,255,0.55)', borderRadius: 1 },
  rear: { width: 12, height: 3, borderRadius: 1, backgroundColor: GLASS },
});
