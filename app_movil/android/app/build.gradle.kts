import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// La firma para publicar en Play Store. Los datos de la llave van en
// android/key.properties, que NO se sube al repositorio (ver README).
val llave = Properties()
val archivoDeLlave = rootProject.file("key.properties")
val hayLlave = archivoDeLlave.exists()
if (hayLlave) {
    llave.load(FileInputStream(archivoDeLlave))
}

android {
    namespace = "pe.edu.colegio.app_colegio"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "pe.edu.colegio.app_colegio"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (hayLlave) {
            create("release") {
                keyAlias = llave["keyAlias"] as String
                keyPassword = llave["keyPassword"] as String
                storeFile = file(llave["storeFile"] as String)
                storePassword = llave["storePassword"] as String
            }
        }
    }

    buildTypes {
        release {
            // Con la llave del colegio si está configurada; si no, con la de
            // desarrollo, que sirve para probar pero no para publicar.
            signingConfig = signingConfigs.getByName(if (hayLlave) "release" else "debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
