import React, { useMemo, useState, useEffect, useRef } from "react";
import { Plus, MoreVertical, Search, LogOut } from "lucide-react";

export default function ChatList({ chats, activeId, onSelect, onLogout }) {
  const [query, setQuery] = useState("");
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);
  const [contacts, setContacts] = useState(chats || []);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    setContacts(chats || []);
  }, [chats]);

  useEffect(() => {
    let aborted = false;
    async function fetchContacts() {
      setLoading(true);
      setError("");
      try {
        const resp = await fetch(
          "http://localhost/whatsapp-backend/backendphp/api/getContacts.php",
          {
            method: "GET",
            credentials: "include",
            headers: { "Content-Type": "application/json" },
          }
        );
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data?.ok !== true) {
          throw new Error(data?.error || "Failed to load contacts");
        }
        if (aborted) return;
        const mapped = (data.contacts || []).map((c) => ({
          id: c.id,
          name: c.name || c.phone_number || "Unknown",
          lastMessage: c.last_message || "",
          time: c.last_seen || "",
          avatar: (c.name || c.phone_number || "?").slice(0, 2).toUpperCase(),
        }));
        setContacts(mapped);
      } catch (e) {
        if (!aborted) setError(e?.message || "Failed to load contacts");
      } finally {
        if (!aborted) setLoading(false);
      }
    }
    fetchContacts();
    return () => {
      aborted = true;
    };
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    const list = contacts && contacts.length ? contacts : (chats || []);
    if (!q) return list;
    return list.filter(
      (c) =>
        c.name.toLowerCase().includes(q) ||
        (c.lastMessage || "").toLowerCase().includes(q)
    );
  }, [contacts, chats, query]);

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event) {
      if (menuRef.current && !menuRef.current.contains(event.target)) {
        setMenuOpen(false);
      }
    }

    if (menuOpen) {
      document.addEventListener("mousedown", handleClickOutside);
    }
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [menuOpen]);

  const handleLogout = () => {
    if (typeof onLogout === "function") onLogout();
    setMenuOpen(false);
  };

  return (
    <div className="h-full flex flex-col bg-(--color-panel) text-textPrimary">
      {/* Header */}
      <div className="px-5 h-[70px] flex items-center justify-between border-b border-(--color-border)">
        <div className="font-semibold text-[28px] tracking-tight">
          WhatsApp
        </div>

        <div className="flex items-center gap-2 text-textSecondary">
          <button
            title="New chat"
            className="w-10 h-10 flex items-center justify-center rounded-full hover:bg-[rgba(255,255,255,0.06)] transition-colors"
          >
            <Plus size={22} />
          </button>

          <div className="relative" ref={menuRef}>
            <button
              title="Menu"
              onClick={() => setMenuOpen(!menuOpen)}
              className="w-10 h-10 flex items-center justify-center rounded-full hover:bg-[rgba(255,255,255,0.06)] transition-colors"
            >
              <MoreVertical size={22} />
            </button>

            {menuOpen && (
              <div className="absolute right-0 top-12 w-56 bg-[#233138] rounded-xl shadow-xl py-1 z-50">
                <button
                  onClick={handleLogout}
                  className="w-full flex items-center gap-3 px-5 py-3 text-white hover:bg-red-600 transition-colors rounded-lg"
                >
                  <LogOut size={18} />
                  <span className="text-[15px] font-medium">Log out</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Search */}
      <div className="px-4 pt-4 pb-3">
        <div className="flex items-center gap-3 rounded-full bg-(--color-inputBg) px-4 py-2.5 h-[45px] text-textSecondary">
          <Search size={16} className="ml-1" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search or start a new chat"
            className="bg-transparent outline-none text-[14.2px] flex-1 text-textPrimary placeholder:text-textSecondary"
          />
        </div>
      </div>

      {/* Chat List */}
      <div className="flex-1 overflow-y-auto px-3 pb-3">
        <div className="space-y-3">
          {loading && (
            <div className="px-5 py-3 text-textSecondary">Loading contacts...</div>
          )}
          {error && (
            <div className="px-5 py-3 text-red-400">{error}</div>
          )}
          {filtered.map((c) => {
            const active = c.id === activeId;
            return (
              <div
                key={c.id}
                onClick={() => onSelect(c.id)}
                className={`flex items-center gap-4 px-5 py-4 rounded-2xl cursor-pointer transition-all duration-200 ${
                  active
                    ? "bg-[rgba(255,255,255,0.08)]"
                    : "hover:bg-[rgba(255,255,255,0.04)]"
                }`}
              >
                {/* Avatar */}
                <div className="w-[54px] h-[54px] rounded-full bg-(--color-border) flex items-center justify-center text-[18px] font-medium shrink-0">
                  {c.avatar}
                </div>

                {/* Chat Info */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-baseline justify-between mb-[4px]">
                    <div className="font-medium text-[16.8px] truncate">
                      {c.name}
                    </div>
                    <div className="text-[12px] text-textSecondary shrink-0">
                      {c.time || ""}
                    </div>
                  </div>
                  <div className="text-[14px] text-textSecondary truncate leading-[20px]">
                    {c.lastMessage}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
