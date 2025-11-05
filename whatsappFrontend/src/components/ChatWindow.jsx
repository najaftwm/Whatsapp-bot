// src/components/ChatWindow.jsx
import React, { useEffect, useRef, useState } from 'react'
import MessageBubble from './MessageBubble'
import MessageInput from './MessageInput'
import TypingIndicator from './TypingIndicator'
import { channel, simulateIncoming } from '../pusherClient'
import { messagesByChat as initialMessages } from '../mockData'
import { motion, AnimatePresence } from 'framer-motion'
import { Search, Paperclip, MoreVertical } from 'lucide-react'

export default function ChatWindow({ chatId, chatInfo }) {
  // messages state for the active chat
  const [messages, setMessages] = useState(() => initialMessages[chatId]?.slice() || [])
  const [typing, setTyping] = useState(false)
  const bottomRef = useRef(null)

  // When chatId changes, load messages for that chat (clone to avoid mutating mock)
  useEffect(() => {
    setMessages(initialMessages[chatId]?.slice() || [])
    setTyping(false)
  }, [chatId])

  // Bind to mocked pusher channel events for this chat
  useEffect(() => {
    function onNew(msg) {
      if (msg.chatId !== chatId) return
      const incoming = {
        id: String(Date.now()) + Math.random().toString(36).slice(2, 7),
        from: 'customer',
        body: msg.body,
        time: msg.time || new Date().toISOString(),
        status: 'delivered',
      }
      setMessages(prev => [...prev, incoming])
    }

    function onTyping(payload) {
      if (payload.chatId === chatId) setTyping(true)
    }
    function onStopTyping(payload) {
      if (payload.chatId === chatId) setTyping(false)
    }

    channel.bind('new-message', onNew)
    channel.bind('typing', onTyping)
    channel.bind('stop-typing', onStopTyping)

    return () => {
      channel.unbind('new-message', onNew)
      channel.unbind('typing', onTyping)
      channel.unbind('stop-typing', onStopTyping)
    }
  }, [chatId])

  // Auto-scroll to bottom when messages or typing changes
  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [messages, typing])

  // Typing handlers for when user types
  function handleTyping(chatId) {
    channel.emitMock?.('typing', { chatId, from: 'me' })
  }

  function handleStopTyping(chatId) {
    channel.emitMock?.('stop-typing', { chatId, from: 'me' })
  }

  // Send handler (from MessageInput)
  function send(text) {
    const id = String(Date.now()) + Math.random().toString(36).slice(2, 7)
    const newMsg = {
      id,
      from: 'me',
      body: text,
      time: new Date().toISOString(),
      status: 'sent', // initial: sent (single tick)
    }

    // add to local messages immediately
    setMessages(prev => [...prev, newMsg])

    // simulate status progression: sent -> delivered -> read
    setTimeout(() => updateStatus(id, 'delivered'), 900)   // 0.9s -> delivered (2 gray ticks)
    setTimeout(() => updateStatus(id, 'read'), 2200)       // 2.2s -> read (2 blue ticks)

    // simulate remote typing + reply after a short delay
    // show typing event
    setTimeout(() => {
      channel.emitMock?.('typing', { chatId })
      setTimeout(() => channel.emitMock?.('stop-typing', { chatId }), 1500)
    }, 800)

    // then simulate an incoming message (customer reply)
    simulateIncoming(chatId, `Got your message: "${text}"`, 3000)
  }

  // update status helper
  function updateStatus(messageId, status) {
    setMessages(prev => prev.map(m => (m.id === messageId ? { ...m, status } : m)))
  }

  return (
    <div className="flex flex-col h-full">
      {/* Header */}
      <div className="flex items-center gap-5 px-7 h-18 bg-(--color-panelElevated) text-textPrimary border-b border-(--color-border)">
        <div className="relative w-10 h-10 rounded-full bg-(--color-border) flex items-center justify-center font-semibold">
          {chatInfo?.avatar || 'C'}
          {chatInfo?.online && (
            <span className="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-green-500 rounded-full ring-2 ring-(--color-panelElevated)" />
          )}
        </div>
        <div className="flex-1 min-w-0">
          <div className="font-medium truncate text-[16px]">{chatInfo?.name || 'Unknown'}</div>
          <div className="text-[13px] text-textSecondary truncate">{chatInfo?.online ? 'Online' : 'Offline'}</div>
        </div>
        <div className="flex items-center gap-3 text-textSecondary">
          <button title="Search" className="p-3 hover:text-textPrimary"><Search size={22} /></button>
          <button title="Attach" className="p-3 hover:text-textPrimary"><Paperclip size={22} /></button>
          <button title="Menu" className="p-3 hover:text-textPrimary"><MoreVertical size={22} /></button>
        </div>
      </div>

      {/* Messages Area */}
      <div className="flex-1 px-10 pt-8 pb-6 overflow-y-auto hide-scrollbar">
        <div className="space-y-3">
          <AnimatePresence initial={false}>
            {messages.map(m => (
              <motion.div
                key={m.id}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18 }}
              >
                <MessageBubble m={m} isMine={m.from === 'me'} />
              </motion.div>
            ))}
          </AnimatePresence>

          {/* Typing indicator */}
          {typing && (
            <div className="flex items-start">
              <TypingIndicator />
            </div>
          )}

          {/* Dummy spacer to scroll into view */}
          <div ref={bottomRef} />
        </div>
      </div>

      {/* Input */}
      <div className="border-t bg-white">
        <MessageInput 
          onSend={send} 
          chatId={chatId}
          onTyping={handleTyping}
          onStopTyping={handleStopTyping}
        />
      </div>
    </div>
  )
}
