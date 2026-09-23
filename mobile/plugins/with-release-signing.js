const { withAppBuildGradle } = require("expo/config-plugins");

const MARKER = "FAMILY_PLAN_RELEASE_STORE_FILE";

/**
 * Appended rather than edited in place, so a new Expo template cannot move it.
 * Without the properties a release build keeps the template's debug key.
 */
const RELEASE_SIGNING = `
// Release key from Gradle properties, e.g. ORG_GRADLE_PROJECT_FAMILY_PLAN_RELEASE_STORE_FILE in CI.
if (project.hasProperty('${MARKER}')) {
    android {
        signingConfigs {
            release {
                storeFile file(FAMILY_PLAN_RELEASE_STORE_FILE)
                storePassword FAMILY_PLAN_RELEASE_STORE_PASSWORD
                keyAlias FAMILY_PLAN_RELEASE_KEY_ALIAS
                keyPassword FAMILY_PLAN_RELEASE_KEY_PASSWORD
            }
        }
        buildTypes {
            release {
                signingConfig signingConfigs.release
            }
        }
    }
}
`;

const withReleaseSigning = (config) =>
  withAppBuildGradle(config, (cfg) => {
    if (cfg.modResults.language !== "groovy") {
      throw new Error("with-release-signing expects android/app/build.gradle in Groovy");
    }
    if (!cfg.modResults.contents.includes(MARKER)) {
      cfg.modResults.contents += RELEASE_SIGNING;
    }
    return cfg;
  });

module.exports = withReleaseSigning;
