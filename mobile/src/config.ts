import { Platform } from 'react-native';

// An Android emulator reaches the Mac's localhost at 10.0.2.2.
const fallback = Platform.OS === 'android' ? 'http://10.0.2.2:8000/api' : 'http://localhost:8000/api';

export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? fallback).replace(/\/$/, '');
