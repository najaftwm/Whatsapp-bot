import {nanoid} from 'nanoid'


export const chats = [
{
id: 'c1',
name: 'Asha',
lastMessage: 'See you at 3!',
avatar: 'A',
online: true,
},
{
id: 'c2',
name: 'Rahul',
lastMessage: 'Sent the report',
avatar: 'R',
online: false,
},
]


const now = () => new Date().toISOString()


export const messagesByChat = {
c1: [
{ id: nanoid(), from: 'customer', body: 'Hi there!', time: now(), status: 'read' },
{ id: nanoid(), from: 'me', body: 'Hello Asha, how can I help?', time: now(), status: 'read' },
],
c2: [
{ id: nanoid(), from: 'customer', body: 'Can you check the file?', time: now(), status: 'delivered' },
{ id: nanoid(), from: 'me', body: 'On it — will update soon.', time: now(), status: 'delivered' },
],
}