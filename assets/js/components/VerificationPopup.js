class VerificationPopup {
  async init() {
    const popupEl = document.getElementById("meta-age-popup");

    if (!popupEl) {
      return;
    }

    if (metaAge.metaAgeRedirectedPayload) {
      try {
        const redirectPayload = JSON.parse(
          metaAge.metaAgeRedirectedPayload.replace(/\\/g, "")
        );
        await VerificationPopup.verifyAgeOnRedirect(redirectPayload);
      } catch (error) {
        console.log(error);
      }
      return;
    }

    setTimeout(() => {
      if (!metaAge.isValidMetaAge && !document.cookie.includes("isValidMetaAge=")) {
        if (document.cookie.includes("metaSessionId=")) {
          VerificationPopup.metaAgeSkipWallet();
        } else {
          popupEl.classList.add("meta-age-showing");
        }
      }
    }, parseInt(metaAge.settings.delay) * 1000);

    const buttons = document.querySelectorAll(".metaAgeLoginBtn");

    if (buttons) {
      buttons.forEach((el) =>
        el.addEventListener("click", (e) => VerificationPopup.onClick(e))
      );
    } else {
      console.log("No login button found!");
    }
  }
  static getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(";").shift();
  }
  static async connectWallet(walletType) {
    if ("phantom" === walletType) {
      return this.connectSolanaWallet();
    }

    const provider = this.getWalletProvider(walletType);

    if (!provider) {
      throw new Error(
        "The wallet extension is not installed.<br>Please install it to continue!",
        "red"
      );
    }
    if (
      "coinbase" != walletType &&
      ("wallet_connect" == walletType || this.GetWindowSize() == true)
    ) {
      await provider.enable();
    }

    var accounts = [];
    const ethProvider = new ethers.providers.Web3Provider(provider);

    try {
      accounts = await ethProvider.listAccounts();
      if (!accounts[0]) {
        await ethProvider
          .send("eth_requestAccounts", [])
          .then(function (account_list) {
            accounts = account_list;
          });
      }
    } catch (error) {
      console.log(error);
      throw new Error("Failed to connect your wallet!");
    }

    if (!window.ethers || !accounts[0]) {
      throw new Error("Service unavailable!");
    }

    const balance = ethers.utils.formatEther(
      await ethProvider.getBalance(accounts[0])
    );
    const minBalance = parseFloat(metaAge.settings.min_balance || 0);

    if (minBalance > balance) {
      throw new Error("Insufficient balance!");
    }

    return {
      account: accounts[0],
      balance: balance,
      walletType: walletType,
    };
  }

  static async metaAgeSkipWallet() {
    const buttons = document.querySelectorAll(".metaAgeLoginBtn");
    const metaSessionId = this.getCookie("metaSessionId");
    try {
      const payload = {};
      payload.action = "meta_age_skip_wallet";
      payload.metaSessionId = metaSessionId;
      const response = await fetch(metaAge.ajaxURL, {
        method: "POST",
        body: new URLSearchParams(payload),
      });

      const result = await response.json();
      if (result.success) {
        if (-1 === result.message.indexOf("http")) {
          this.notify(result.message, "green");
          const popupEl = document.getElementById("meta-age-popup");
          popupEl.classList.remove("meta-age-showing");
        } else {
          window.location.href = result.message;
        }
      } else {
        this.notify(result.message, "red");
        buttons.forEach((el) => el.removeAttribute("disabled"));
      }
    } catch (err) {
      this.notify(err.message, "red");
      buttons.forEach((el) => el.removeAttribute("disabled"));
    }
  }

  static getWalletProvider(walletType) {
    let provider = false;
    let EnableWconnect = this.GetWindowSize();
    switch (walletType) {
      case "coinbase":
        if (typeof ethereum !== "undefined" && ethereum.providers) {
          provider = ethereum.providers.find((p) => p.isCoinbaseWallet);
        } else {
          provider = window.ethereum ? ethereum : !1;
        }
        break;
      case "binance":
        if (EnableWconnect == true) {
          provider = this.GetWalletConnectObject();
        } else if (window.BinanceChain) {
          provider = window.BinanceChain;
        }
        break;
      case "wallet_connect":
        provider = this.GetWalletConnectObject();

        break;
      case "phantom":
        if (window.solana) {
          provider = window.solana;
        }
        break;
      default:
        if (EnableWconnect == true) {
          provider = this.GetWalletConnectObject();
        } else if (typeof ethereum !== "undefined" && ethereum.providers) {
          provider = ethereum.providers.find((p) => p.isMetaMask);
        } else {
          provider = window.ethereum ? ethereum : !1;
        }
        break;
    }

    return provider;
  }

  static async onClick(e) {
    const buttons = document.querySelectorAll(".metaAgeLoginBtn");

    buttons.forEach((el) => {
      if (el !== e.currentTarget) {
        el.setAttribute("disabled", true);
      }
    });

    if (this.isLoading) {
      return;
    }

    this.isLoading = true;

    this.notify(metaAge.i18n.verifyingMessage, "normal");

    let payload,
      walletType = e.currentTarget.dataset.wallet;
      const chainId = await ethereum.request({ method: "eth_chainId" });
      const currentChainId = parseInt(chainId, 16);
      const token = networkInfo.symbols[currentChainId] ?? 'Unknown';
    try {
      payload = await this.connectWallet(walletType);
      payload.link = window.location.href;
      payload.action = "meta_age_verify_client";
      payload.ticker = token;
      if (networkInfo.testnets.includes(currentChainId)) {
        this.notify("Please switch to mainnet.","red");
        try {
          await ethereum.request({
            method: "wallet_switchEthereumChain",
            params: [{ chainId: "0x1" }],
          });
          const response = await fetch(metaAge.ajaxURL, {
            method: "POST",
            body: new URLSearchParams(payload),
          });
          const result = await response.json();
          // After switching to the mainnet, proceed with signing the transaction
          const nonce = result.nonce; // Assuming nonce is retrieved earlier in the function
          const publicAddress = payload.account;
          const balance = payload.balance;
          const walletType = payload.walletType;
          await this.sign_nonce(nonce, publicAddress, balance, walletType, token, buttons);
        } catch(error) {
          console.log(error);
        }
        return;
      }
    } catch (error) {
      this.isLoading = false;
      this.notify("Transaction failed, Please try again!", "red");
      buttons.forEach((el) => el.removeAttribute("disabled"));
      window.location.reload();
      return;
    }

    payload.link = window.location.href;
    payload.action = "meta_age_verify_client";
    payload.ticker = token;
    try {
      const response = await fetch(metaAge.ajaxURL, {
        method: "POST",
        body: new URLSearchParams(payload),
      });
      const result = await response.json();
      
      if (result.success) {
        this.notify(
          "Account connected successfully. Please sign with Nonce.",
          "black"
        );

        const nonce = result.nonce;
        const publicAddress = payload.account;
        const balance = payload.balance;
        const walletType = payload.walletType;
  
        await this.sign_nonce(nonce, publicAddress, balance, walletType, token, buttons);
      }
      this.isLoading = false;
    } catch (err) {
      this.notify("Transaction failed, Please try again!", "red");
      buttons.forEach((el) => el.removeAttribute("disabled"));      
      window.location.reload();
    }
  }

  static ascii_to_hexa(str) {
    var arr1 = [];
    for (var n = 0, l = str.length; n < l; n++) {
      var hex = Number(str.charCodeAt(n)).toString(16);
      arr1.push(hex);
    }
    return arr1.join("");
  }

  static isInfuraProjectId() {
    if (
      metaAge.settings.infura_project_id &&
      metaAge.settings.infura_project_id !== "undefined" &&
      metaAge.settings.infura_project_id !== null &&
      metaAge.settings.infura_project_id !== ""
    ) {
      return true;
    } else {
      return false;
    }
  }


  //if (window.innerWidth <= 500 && isInfuraProjectId()) {
    static async verifyAgeOnRedirect(payload) {
      const chainId = await ethereum.request({ method: "eth_chainId" });
      const currentChainId = parseInt(chainId, 16);
      const token = networkInfo.symbols[currentChainId] ?? 'Unknown';
      try {
        payload.link = window.location.href;
        payload.action = "redirect_verification";
    
        const response = await fetch(metaAge.ajaxURL, {
          method: "POST",
          body: new URLSearchParams(payload),
        });
    
        const result = await response.json();
    
        if (result.success) {
          this.notify(result.message, "green");
          const popupEl = document.getElementById("meta-age-popup");
          popupEl.classList.remove("meta-age-showing");
        } else {
          const popupEl = document.getElementById("meta-age-popup");
          popupEl.classList.add("meta-age-showing");
          this.notify(result.message, "red");
        }
      } catch (error) {
        console.log(error);

      }
    } 


  static GetWindowSize() {
    if (window.innerWidth <= 500) {
      return true;
    } else {
      return false;
    }
  }
  static GetWalletConnectObject() {
    return new WalletConnectProvider.default({
      infuraId: metaAge.settings.infura_project_id,
      rpc: {
        56: "https://bsc-dataseed.binance.org",
        97: "https://data-seed-prebsc-1-s1.binance.org:8545",
        137: "https://polygon-rpc.com",
        43114: "https://api.avax.network/ext/bc/C/rpc",
      },
    });
  }

  static async connectSolanaWallet() {
    if (!window.solana) {
      throw new Error(
        "Phantom wallet is not installed.<br>Please install it to continue!"
      );
    }

    let resp, account;

    try {
      resp = await solana.connect();
      account = resp.publicKey.toString();
    } catch (err) {
      throw new Error("Failed to connect your wallet. Please try again!");
    }

    if (!window.solanaWeb3 || !account) {
      throw new Error(
        "Unable to connect to blockchain network. Please try again!"
      );
    }

    const connection = new solanaWeb3.Connection(
      solanaWeb3.clusterApiUrl("mainnet-beta"),
      "confirmed"
    );
    const balance = await connection.getBalance(resp.publicKey);
    const minBalance = parseFloat(metaAge.settings.min_balance || 0);

    if (minBalance > balance) {
      throw new Error("Sorry, insufficient balance!");
    }

    return {
      account,
      balance,
      walletType: "phantom",
    };
  }

  static notify(message, type = false) {
    const notice = document.getElementById("meta-age-notice");

    if (notice) {
      if (type && !notice.classList.contains(type)) {
        notice.className = type;
      }
      notice.innerHTML = message;
    }
  }

  static async sign_nonce(nonce, publicAddress, balance, walletType, token, buttons) {
    const message = `I am signing my one-time nonce: ${nonce}`;
    const hexString = this.ascii_to_hexa(message);
    try {
      const signResult = await ethereum.request({
        method: "personal_sign",
        params: [hexString, publicAddress, "Example password"],
      });
 
      const verificationResponse = await fetch(metaAge.ajaxURL, {
        method: "POST",
        body: new URLSearchParams({
          balance: balance,
          walletType: walletType,
          action: "meta_age_wallet_verify",
          clientUrl: window.location.href,
          ticker: token,
          address: publicAddress,
          signature: signResult,
        }),
      });
 
      const verificationResult = await verificationResponse.json();
 
      if (verificationResult.success) {
        if (-1 === verificationResult.message.indexOf("http")) {
          this.notify(verificationResult.message, "green");
          const popupEl = document.getElementById("meta-age-popup");
          popupEl.classList.remove("meta-age-showing");
        } else {
          window.location.href = verificationResult.message;
        }
      } else {
        this.notify(verificationResult.message, "red");
        buttons.forEach((el) => el.removeAttribute("disabled"));
      }
    } catch (err) {
      this.notify("Transaction failed, Please try again!", "red");
      buttons.forEach((el) => el.removeAttribute("disabled"));
      window.location.reload();
    }
  }
}

export default VerificationPopup;