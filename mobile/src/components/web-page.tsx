import { StyleSheet } from 'react-native';
import { WebView } from 'react-native-webview';

/** Shows a web page inside the app, filling the space it is given. On the web build this is an iframe. */
export function WebPage({ url, title }: { url: string; title: string }) {
  return <WebView source={{ uri: url }} style={styles.fill} accessibilityLabel={title} startInLoadingState />;
}

const styles = StyleSheet.create({ fill: { flex: 1 } });
