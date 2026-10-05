import { useLocalSearchParams } from 'expo-router';
import { View } from 'react-native';

import { Notice } from '@/components/notice';
import { WebPage } from '@/components/web-page';

/**
 * Task D5. The live map. It is TrustRide's own web page shown inside the app, the same page a share
 * link opens, so there is one map to maintain and no paid map key. The page updates itself.
 */
export default function TripMapScreen() {
  const { url } = useLocalSearchParams<{ url?: string }>();

  if (!url) {
    return <Notice text="The map isn't ready yet. Go back and try again." />;
  }

  return (
    <View style={{ flex: 1 }}>
      <WebPage url={`${url}${url.includes('?') ? '&' : '?'}embed=1`} title="Map of your van" />
    </View>
  );
}
