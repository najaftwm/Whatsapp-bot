import { useState } from "react";
import { authClient } from "../authClient";
import { Eye, EyeOff } from "lucide-react";

export default function Login({ onLoginSuccess }) {
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const user = await authClient.login(username, password);
      if (onLoginSuccess) onLoginSuccess(user);
    } catch (err) {
      console.error("Login error:", err);
      setError(err?.message || "Login failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative min-h-screen flex items-center justify-center bg-[#0b141a] bg-[url('https://upload.wikimedia.org/wikipedia/commons/0/06/WhatsApp_background.png')] bg-cover bg-center px-8 py-14">
      {/* Dim Overlay */}
      <div className="absolute inset-0 bg-[#0b141a]/70" />

      {/* Login Card */}
      <div
        className="relative w-100 flex flex-col items-center justify-center gap-5  bg-[#111b21] border border-[#2a2f32] rounded-2xl shadow-[0_20px_60px_rgba(0,0,0,0.35)] px-16 py-14 overflow-hidden z-10 box-border"
        style={{ minHeight: "540px" }}
      >
        {/* WhatsApp Logo */}
        <div className="flex justify-center mb-10 mt-4">
          <div className="w-20 h-20 rounded-2xl bg-[#25d366] flex items-center justify-center shadow-lg">
            <svg viewBox="0 0 32 32" className="w-12 h-12 text-white" fill="currentColor">
              <path d="M16 0C7.164 0 0 7.164 0 16c0 2.826.738 5.577 2.137 7.965L.512 30.98c-.15.446.218.814.664.664l7.015-1.625A15.937 15.937 0 0 0 16 32c8.836 0 16-7.164 16-16S24.836 0 16 0zm0 29.333c-2.646 0-5.207-.787-7.393-2.275l-.415-.283-4.302 1.003 1.003-4.302-.283-.415A13.267 13.267 0 0 1 2.667 16C2.667 8.648 8.648 2.667 16 2.667S29.333 8.648 29.333 16 23.352 29.333 16 29.333z" />
              <path d="M23.097 19.46c-.365-.183-2.16-1.066-2.494-1.188-.334-.122-.577-.183-.82.183-.243.365-.94 1.188-1.154 1.431-.213.243-.426.274-.79.091-.365-.183-1.543-.569-2.938-1.813-1.086-.97-1.82-2.168-2.033-2.533-.213-.365-.023-.562.16-.744.165-.165.365-.426.548-.64.183-.213.243-.365.365-.608.122-.243.061-.456-.03-.64-.091-.183-.82-1.975-1.123-2.706-.295-.711-.595-.615-.82-.626-.213-.01-.456-.012-.699-.012s-.64.091-.974.456c-.334.365-1.274 1.245-1.274 3.037s1.305 3.523 1.488 3.766c.183.243 2.58 3.94 6.25 5.526.873.377 1.555.602 2.086.771.876.278 1.673.239 2.304.145.703-.105 2.16-.884 2.464-1.737.304-.853.304-1.584.213-1.737-.091-.152-.334-.243-.699-.426z" />
            </svg>
          </div>
        </div>

        {/* Heading */}
        <div className="text-center mb-12">
          <h1 className="text-[36px] font-xl text-white mb-1">WhatsApp Web</h1>
          <p className="text-sm text-[#8696a0]">Sign in to continue to WhatsApp</p>
        </div>

        {/* Form area with generous spacing */}
        <div className="flex flex-col pt-3 gap-5">
          {/* Username */}
          <div>
            <label className="block text-xs text-[#8696a0] mb-2">Username</label>
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Enter your number"
              className="w-full h-14 px-5 bg-[#1f2c33] text-white rounded-xl border border-[#24323a] hover:border-[#3b4a54] focus:border-[#00a884] focus:ring-2 focus:ring-[#00a884]/50 outline-none transition-all duration-200"
            />
          </div>

          {/* Password */}
          <div>
            <label className="block text-xs text-[#8696a0] mb-2">Password</label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                className="w-full h-14 pl-5 pr-12 bg-[#1f2c33] text-white rounded-xl border border-[#24323a] hover:border-[#3b4a54] focus:border-[#00a884] focus:ring-2 focus:ring-[#00a884]/50 outline-none transition-all duration-200"
              />
              <button
                type="button"
                onClick={() => setShowPassword((v) => !v)}
                className="absolute inset-y-0 right-0 flex items-center px-3 text-[#8696a0] hover:text-white transition-colors"
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-500/10 border border-red-500/40 text-red-300 px-4 py-3 rounded-lg text-sm">
              {error}
            </div>
          )}

          {/* Sign In Button */}
          <button
            onClick={handleSubmit}
            disabled={loading}
            className={`w-full h-14 bg-linear-to-r from-[#00a884] to-[#019974] hover:from-[#00b493] hover:to-[#018d6f] text-white font-medium rounded-xl shadow-[0_12px_32px_rgba(0,168,132,0.35)] focus:outline-none focus:ring-2 focus:ring-[#00a884]/60 transition-all duration-200 ${
              loading ? "opacity-70 cursor-not-allowed" : ""
            }`}
          >
            {loading ? "Signing in..." : "Sign in"}
          </button>
        </div>

        {/* Footer Notice */}
        <div className="mt-10 flex items-center justify-center text-[12px] text-[#8696a0]">
          <svg
            className="w-3.5 h-3.5 mr-2"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
            />
          </svg>
          <span>Your personal messages are end-to-end encrypted</span>
        </div>
      </div>
    </div>
  );
}
