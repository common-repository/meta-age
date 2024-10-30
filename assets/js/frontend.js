import Popup from "./components/VerificationPopup";
import LazyScriptsLoader from "./components/LazyScriptsLoader";

window.addEventListener('DOMContentLoaded', () => {
    const popup = new Popup();
    const lazyScriptsLoader = new LazyScriptsLoader(
        ['load', 'keydown', 'mousemove', 'touchmove', 'touchstart', 'touchend', 'wheel'],
        [
            {
                id: "ethers",
                uri: metaAge.pluginURI + 'assets/js/vendor/ethers.min.js',
            },
            {
                id: "solana",
                uri: metaAge.pluginURI + 'assets/js/vendor/solana.min.js',
            },
            {
                id: "wallet_connect",
                uri: metaAge.pluginURI + 'assets/js/vendor/walletconnect.js'
            },
        ]
    );

    lazyScriptsLoader.init(lazyScriptsLoader);
    popup.init();
});
