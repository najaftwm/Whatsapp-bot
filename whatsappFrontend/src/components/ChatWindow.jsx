import React, { useState, useEffect, useRef } from "react";
import { Send } from "lucide-react";
import { pusher } from "../pusherClient";

export default function ChatWindow({ activeChat, messages }) {
  const [input, setInput] = useState("");
  const [chatMessages, setChatMessages] = useState(messages || []);
  const endRef = useRef(null);

  // Fetch messages when chat changes
  useEffect(() => {
    async function fetchMessages() {
      if (!activeChat) {
        setChatMessages([]);
        return;
      }
      try {
        const res = await fetch(
          `http://localhost/whatsapp-backend/backendphp/api/getMessages.php?contact_id=${activeChat}`,
          { credentials: "include" }
        );
        const data = await res.json();
        if (data?.ok && data.messages) {
          // Map backend message format to frontend format
          const mapped = data.messages.map(m => ({
            id: m.id,
            message: m.message_text || '',
            sender_type: m.sender_type || 'customer',
            timestamp: m.timestamp
          }));
          setChatMessages(mapped);
        }
      } catch (e) {
        console.error("Failed to load messages:", e);
        setChatMessages([]);
      }
    }
    fetchMessages();
  }, [activeChat]);

  // Real-time updates
  useEffect(() => {
    if (!activeChat) return;
    const channel = pusher.subscribe("chat-channel");
    channel.bind("new-message", (data) => {
      if (data.contact_id === activeChat) {
        // Generate unique ID if not provided
        const uniqueId = data.id || `pusher-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
        setChatMessages((prev) => {
          const text = data.message || data.message_text || '';

          // If this is a company (outgoing) message, replace the last optimistic temp one
          if ((data.sender_type || 'customer') === 'company') {
            const lastIdx = [...prev].reverse().findIndex(m => m.sender_type === 'company' && (m.id || '').startsWith('temp-') && m.message === text);
            if (lastIdx !== -1) {
              const idx = prev.length - 1 - lastIdx;
              const next = [...prev];
              next[idx] = {
                id: uniqueId,
                message: text,
                sender_type: 'company',
                timestamp: data.timestamp || new Date().toISOString(),
              };
              return next;
            }
          }

          // Otherwise, prevent exact duplicates
          const exists = prev.some(msg => msg.id === uniqueId || (msg.timestamp === data.timestamp && msg.message === text && msg.sender_type === (data.sender_type || 'customer')));
          if (exists) return prev;

          return [...prev, {
            id: uniqueId,
            message: text,
            sender_type: data.sender_type || 'customer',
            timestamp: data.timestamp || new Date().toISOString(),
          }];
        });
      }
    });
    return () => {
      pusher.unsubscribe("chat-channel");
    };
  }, [activeChat]);

  // Scroll to bottom
  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [chatMessages]);

  // Handle Send
  const handleSend = async () => {
    if (!input.trim()) return;

    const messageText = input.trim();
    setInput("");

    // Local optimistic update
    const tempId = `temp-${Date.now()}`;
    setChatMessages((prev) => [
      ...prev,
      { 
        id: tempId,
        message: messageText, 
        sender_type: "company",
        timestamp: new Date().toISOString()
      },
    ]);

    try {
      await fetch(
        "http://localhost/whatsapp-backend/backendphp/api/sendMessage.php",
        {
          method: "POST",
          credentials: "include",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            contact_id: activeChat,
            message: messageText,
          }),
        }
      );
    } catch (e) {
      console.error("Send failed:", e);
    }
  };

  return (
    <div className="flex flex-col h-full bg-(--color-panel)">
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {chatMessages.map((msg, idx) => (
          <div
            key={msg.id || `msg-${idx}-${msg.timestamp || Date.now()}`}
            className={`flex ${
              msg.sender_type === "company" ? "justify-end" : "justify-start"
            }`}
          >
            <div
              className={`px-4 py-2 rounded-2xl max-w-[75%] text-sm ${
                msg.sender_type === "company"
                  ? "bg-green-600 text-white"
                  : "bg-gray-700 text-gray-100"
              }`}
            >
              {msg.message || msg.message_text}
            </div>
          </div>
        ))}
        <div ref={endRef} />
      </div>

      <div className="p-4 border-t border-(--color-border) flex items-center gap-2">
        <input
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && handleSend()}
          placeholder="Type a message"
          className="flex-1 bg-(--color-inputBg) text-textPrimary px-4 py-2.5 rounded-full outline-none"
        />
        <button
          onClick={handleSend}
          className="p-2.5 bg-green-600 text-white rounded-full hover:bg-green-700 transition"
        >
          <Send size={18} />
        </button>
      </div>
    </div>
  );
}
