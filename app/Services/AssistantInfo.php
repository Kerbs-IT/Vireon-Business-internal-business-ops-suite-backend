<?php

namespace App\Services;

use App\Models\User;

class AssistantInfo
{
    public const TOPICS = [
        'about',
        'capabilities',
        'examples',
        'privacy',
        'limitations',
        'how_it_works',
        'read_only',
        'memory',
        'greeting',
        'thanks',
    ];

    public function reply(string $topic, User $user): string
    {
        // Anything the AI invents falls back to "about".
        $topic = in_array($topic, self::TOPICS, true) ? $topic : 'about';
        $isManager = $user->isManager();
        $name = $user->first_name ?: 'there';

        return match ($topic) {

            'about' => <<<TXT
            I'm the **Vireon AI Assistant**, built into Vireon: Business Internal Suite.

            I answer questions about your work using real data from the system: tasks, projects, and leave. I don't make up numbers, because every figure comes from the database.

            Ask "what can you do?" to see everything I can help with.
            TXT,

            'capabilities' => "Here's what I can help with:\n\n"
                . "- **Tasks:** overdue, due today or this week, by project, by priority, and what you finished\n"
                . "- **Leave:** your balance, your requests, and whether you can take a date\n"
                . "- **Projects:** status, task counts, and members\n"
                . "- **Team:** who is on leave and who is available\n"
                . "- **Reports:** a summary of your week or month\n"
                . ($isManager
                    ? "- **Management:** pending leave approvals, workload, overdue tasks per employee, and which projects are behind\n"
                    : '')
                . "\nAsk \"give me example questions\" if you want ideas.",

            'examples' => "Try asking:\n\n"
                . "- Which of my tasks are overdue?\n"
                . "- What is due tomorrow?\n"
                . "- What are my high priority tasks?\n"
                . "- How many leave days do I have left?\n"
                . "- Can I take leave next Friday?\n"
                . "- What's the status of Vireon?\n"
                . "- Who is working on Vireon?\n"
                . "- Give me my weekly report\n"
                . ($isManager
                    ? "\nAs a manager you can also ask:\n\n"
                    . "- Which leave requests are waiting for my approval?\n"
                    . "- Who has overdue tasks?\n"
                    . "- Who has the most open tasks?\n"
                    . "- Who is available next week?\n"
                    : ''),

            'privacy' => <<<TXT
            I only see what your role allows.

            - Employees see their own tasks, leave, and the projects they belong to.
            - Managers can also see team-level information.
            - Private notes, such as the comment on a leave request, are not shared with me.

            To write an answer, the relevant data is sent to an external AI service. Your questions and answers are not used to change anything in the system.
            TXT,

            'limitations' => <<<TXT
            I'm helpful, but not perfect:

            - I can make mistakes, so please verify important information.
            - I can only answer the topics I'm set up for. Anything else, I'll tell you I can't answer yet.
            - I report facts. I don't judge anyone's performance, and I don't decide leave approvals.
            - I see recent data only, not full history.
            TXT,

            'how_it_works' => <<<TXT
            Here's how I work:

            1. You ask a question.
            2. The system works out which topic it belongs to.
            3. It fetches only the data your role allows from the database and does the counting and date math.
            4. I write the answer from that data.

            Because the numbers come from the database and not from me, they stay accurate.
            TXT,

            'read_only' => <<<TXT
            I can only **read and explain** your data. I can't create or edit tasks, update statuses, submit or approve leave requests, or delete anything.

            To make changes, please use the relevant page in Vireon. I'm happy to help you find the information first.
            TXT,

            'memory' => <<<TXT
            I remember only your last few questions in this conversation, which helps with follow-ups like "how about Vireon?"

            I don't keep a long-term memory. Refreshing the page clears the conversation.
            TXT,

            'greeting' => "Hi {$name}! I'm the Vireon AI Assistant. Ask me about your tasks, leave, or projects, or type \"what can you do?\" to see all of it.",

            'thanks' => "You're welcome, {$name}! Ask me anything else about your tasks, leave, or projects.",
        };
    }
}
