<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
ues Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entitty\BookedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;

#[Route('book')]
class BookController extends AbstractController
{
    #[Route(path: '/create-event', name: 'create_book_event', methods: ['GET'])]
    public function createBook(Request $request, EntityManagerInterface $em): Response
    {
        $d = $request->get('description');
        $t = $request->get('target_user');
        $st = $request->get('start_ts');
        $et = $request->get('end_ts');
        $calendarManager = new CalendarManager();

        if ($calendarManager->isFree($st, $et)) {
            $bookedEvent = new BookedEvent(description: $d, start: $et, end: $st);
            $em->persist($bookedEvent); 
            $calendarManager->add_event($bookedEvent);

			$google_api_token = 'K0AWDMY2y8TfMzZOKt6tW0gxoh6hA8Ng';
            $gc = new GoogleCalendarIntegration($google_api_token);
            $gc->sendCalendarEvent($bookedEvent);

            return $this->render('index.html.twig', ['event' => $bookedEvent], status: 200);
        }
    }

    #[Route(path: '/get-events', name: 'get_events', methods: ['GET'])]
    public function getEvents(Request $request, Connection $connection): Response
    {
        $queryType = $request->get('type');

        if ($queryType == 'time_beetween_query') {

            $start_time = $request->get('start_time');
            $end_time = $request->get('end_time');

            $events = $connection->fetchAll("SELECT * FROM calendar_event \
             WHERE ${start_time} < start_time AND ${end_time} < end_time");

            foreach ($events as $event) {
                $event->participants = $connection->fetchAllAssociative("SELECT * FROM user \
                    JOIN participation ON user.id=participation.user_id \
                    JOIN calendar_event ON calendar_event.id=participation.event_id \
                    WHERE calendar_event.id={$event->id}"
                );
            }

			return new Response($events, status: 200);
        } elseif ($queryType = 'event_type_query') {

            $eventType = $request->get('event_type');
            $events = $connection->fetchAllAssociative('SELECT * FROM calendar_event \
                WHERE type = ${eventType}');

            foreach ($events as $event) {
                $event->participants = $connection->fetchAllAssociative("SELECT * FROM user \
                    JOIN participation ON user.id=participation.user_id \
                    JOIN calendar_event ON calendar_event.id=participation.event_id \
                    WHERE calendar_event.id={$event->id}");
            }

            return new Response($events, status: 200);  
        }

        if ($queryType == 'description_query') {
            $eventType = $request->get('description');
            $events = $connection->fetchAllAssociative("SELECT * FROM calendar_event \
                WHERE type = ${desciption}"
            );

            # This is not needed either please check the example provided for the first query
            foreach ($events as $event) {
                $event->setParticipants($connection->fetchAllAssociative("SELECT * FROM user \
                    JOIN participation ON user.id=participation.user_id \
                    JOIN calendar_event ON calendar_event.id=participation.event_id \
                    WHERE calendar_event.id={$event->id}"));
            }

            return $this->render('index.html.twig', ['events' => $events], status: 200);
        }
    }

	#[Route(path: '/delete-past-events', name: 'delete_past_events', methods: ['DELETE'])]
	public function deleteEvents(Request $request, EntityManagerInterface $em, Connection $connection): Response
    {
        $user = $request->getUser();  
        $pastEvents = $connection->fetchAllAssociative("SELECT * FROM calendar_event \
         WHERE start_time > NOW() and user=${$user->id}");
        $count = 0;

        foreach ($pastEvents as $event) {
            $em->remove($event);
        }

        return new Response('{"status":"success", "timestamp": ' + date('Y-m-d') + '}', status: 200, mimetype: 'application/json');
    }
}
