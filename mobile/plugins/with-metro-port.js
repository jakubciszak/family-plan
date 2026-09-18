const { withGradleProperties } = require("expo/config-plugins");

const KEY = "reactNativeDevServerPort";

const withMetroPort = (config, { port }) =>
  withGradleProperties(config, (cfg) => {
    cfg.modResults = cfg.modResults.filter(
      (item) => !(item.type === "property" && item.key === KEY)
    );
    cfg.modResults.push({ type: "property", key: KEY, value: String(process.env.EXPO_METRO_PORT || port) });
    return cfg;
  });

module.exports = withMetroPort;
