/*
Frontend-only mocked Pusher. This file keeps the real pusher import (placeholder) but also
provides a `channel` object that supports `bind` (listen) and an internal `emitMock` to simulate
server events. Your backend later can replace this file with a real Pusher setup.
*/


// import Pusher from 'pusher-js' // keep if you swap to a real Pusher setup


class MockChannel {
    constructor() {
    this.handlers = {}
    }
    bind(event, cb) {
    if (!this.handlers[event]) this.handlers[event] = []
    this.handlers[event].push(cb)
    }
    unbind(event, cb) {
    if (!this.handlers[event]) return
    this.handlers[event] = this.handlers[event].filter(h => h !== cb)
    }
    emitMock(event, payload) {
    const handlers = this.handlers[event] || []
    handlers.forEach(h => h(payload))
    }
    }
    
    
    export const channel = new MockChannel()
    
    
    // Simulate an incoming message after 4s (for demo). In a real setup remove this and let Pusher deliver messages.
    setTimeout(() => {
    channel.emitMock('new-message', {
    chatId: 'c1',
    from: 'customer',
    body: "Thanks for reaching out! We'll get back in a bit.",
    time: new Date().toISOString(),
    })
    }, 4000)
    
    
    // simulate typing
    setTimeout(() => {
    channel.emitMock('typing', { chatId: 'c1', from: 'customer' })
    setTimeout(() => channel.emitMock('stop-typing', { chatId: 'c1' }), 2000)
    }, 2000)
    
    
    // Export a convenience function to trigger mock receives (useful for tests)
    export function simulateIncoming(chatId, body, delay = 3500) {
    setTimeout(() => {
    channel.emitMock('new-message', {
    chatId,
    from: 'customer',
    body,
    time: new Date().toISOString(),
    })
    }, delay)
    }