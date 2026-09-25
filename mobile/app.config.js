const fs = require('fs');
const path = require('path');

/**
 * Firebase Cloud Messaging needs google-services.json of the Firebase project, which only the release build has
 * (the CI writes it from the GOOGLE_SERVICES_JSON secret). Without it the app builds as before and phone push
 * stays off.
 */
module.exports = ({ config }) => {
  const file = process.env.GOOGLE_SERVICES_FILE || path.join(__dirname, 'google-services.json');

  if (!fs.existsSync(file)) {
    return config;
  }

  return {
    ...config,
    android: { ...config.android, googleServicesFile: `./${path.relative(__dirname, file)}` },
  };
};
