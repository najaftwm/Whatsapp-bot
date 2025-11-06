// src/App.jsx
import React, { useState, useEffect } from 'react'
import ChatList from './components/ChatList'
import ChatWindow from './components/ChatWindow'
import Login from './components/Login'
import { authClient } from './authClient'
import { ArrowLeft } from 'lucide-react'

export default function App() {
  const [activeChatId, setActiveChatId] = useState(null)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [isAuthed, setIsAuthed] = useState(authClient.isAuthenticated())
  const [contacts, setContacts] = useState([])

  // Fetch contacts when authenticated
  useEffect(() => {
    if (!isAuthed) return;
    async function fetchContacts() {
      try {
        const resp = await fetch(
          "http://localhost/whatsapp-backend/backendphp/api/getContacts.php",
          {
            method: "GET",
            credentials: "include",
            headers: { "Content-Type": "application/json" },
          }
        );
        const data = await resp.json();
        if (data?.ok && data.contacts) {
          // Map contacts to match ChatList format
          const mapped = data.contacts.map((c) => ({
            id: c.id,
            name: c.name || c.phone_number || "Unknown",
            phone_number: c.phone_number,
            last_message: c.last_message || "",
            last_seen: c.last_seen || "",
            avatar: (c.name || c.phone_number || "?").slice(0, 2).toUpperCase(),
          }));
          setContacts(mapped);
        }
      } catch (e) {
        console.error("Failed to load contacts:", e);
      }
    }
    fetchContacts();
  }, [isAuthed]);

  function handleLogout() {
    authClient.logout()
    setIsAuthed(false)
  }

  useEffect(() => {
    // keep auth state in sync if storage changes in other tabs
    function onStorage(e) {
      if (e.key === 'isAuthenticated') {
        setIsAuthed(authClient.isAuthenticated())
      }
    }
    window.addEventListener('storage', onStorage)
    return () => window.removeEventListener('storage', onStorage)
  }, [])

  // Find active chat info from contacts
  const activeChat = activeChatId ? contacts.find(c => c.id === activeChatId) : null

  function handleSelect(chatId) {
    setActiveChatId(chatId)
    // if on mobile, open the chat window panel
    setMobileOpen(true)
  }

  if (!isAuthed) {
    return <Login onLoginSuccess={() => setIsAuthed(true)} />
  }

  return (
    <div className="min-h-screen bg-(--color-chatBg) flex items-center justify-center">
      <div className="hidden md:flex w-full max-w-[1600px]">
        {/* Left column: Chat list */}
        <div className="w-[30%] bg-(--color-panel) border-r border-(--color-border)">
          <div className="h-screen max-h-screen px-5 pt-3 pb-2">
            <ChatList chats={contacts} activeId={activeChatId} onSelect={handleSelect} onLogout={handleLogout} />
          </div>
        </div>
        {/* Right column: Chat window */}
        <div className="w-[70%]">
          <div className="h-screen max-h-screen wa-wallpaper">
            {activeChatId ? (
              <ChatWindow activeChat={activeChatId} />
            ) : (
              <div className="flex items-center justify-center h-full text-textSecondary">
                Select a contact to start chatting
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Mobile layout */}
      <div className="w-full md:hidden">
        {!mobileOpen && (
          <div className="h-screen bg-(--color-panel) overflow-hidden px-5 pt-3 pb-2">
            <ChatList chats={contacts} activeId={activeChatId} onSelect={handleSelect} onLogout={handleLogout} />
          </div>
        )}

        {mobileOpen && (
          <div className="fixed inset-0 z-40 flex flex-col bg-(--color-chatBg) wa-wallpaper">
            {/* Mobile header with back button */}
            <div className="flex items-center px-5 h-16 bg-(--color-panelElevated) text-white">
              <button
                onClick={() => setMobileOpen(false)}
                className="mr-4 p-2 rounded-full hover:bg-white/10"
                aria-label="Back"
              >
                <ArrowLeft size={22} />
              </button>
              <div className="flex-1">
                <div className="font-semibold text-[16px]">{activeChat?.name || 'Chat'}</div>
                <div className="text-[13px] opacity-70">{activeChat?.phone_number || 'Offline'}</div>
              </div>
            </div>

            <div className="flex-1">
              {activeChatId ? (
                <ChatWindow activeChat={activeChatId} />
              ) : (
                <div className="flex items-center justify-center h-full text-textSecondary">
                  Select a contact to start chatting
                </div>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
