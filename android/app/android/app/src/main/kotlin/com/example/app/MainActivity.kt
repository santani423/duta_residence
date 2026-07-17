package com.example.app

import io.flutter.embedding.android.FlutterFragmentActivity

// local_auth's biometric prompt (androidx.biometric.BiometricPrompt) requires
// a FragmentActivity host - a plain FlutterActivity is not enough.
class MainActivity : FlutterFragmentActivity()
