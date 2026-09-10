<?php

namespace App\Http\Controllers\Client;

use App\Support\ClientPortal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The client's projects, and the daily updates on one of them.
 *
 * A client sees progress, deadlines and what we have told them — never the task
 * board. Tasks carry assignees, internal status vocabulary and their own
 * visibility questions; the client-facing unit of progress is the project and
 * the update log, which is what these two pages are.
 */
class ProjectController extends PortalController
{
    public function index(Request $request): Response
    {
        $client = $this->client($request);

        return response()->view('client.projects.index', $this->shell($request, 'projects') + [
            'projects' => ClientPortal::projects($client)->map(
                fn (array $project) => $this->decorate($project)
            ),
            'stats' => ClientPortal::stats($client)['projects'],
        ]);
    }

    public function show(Request $request, string $project): Response
    {
        $client = $this->client($request);

        $record = ClientPortal::project($client, $project);

        /*
         * Null covers both "no such project" and "not yours", and they get the
         * SAME 404 on purpose. Two different answers would let somebody walk
         * the id space and learn which references are real, which is the first
         * half of the leak §6 is about.
         */
        abort_if($record === null, 404);

        return response()->view('client.projects.show', $this->shell($request, 'projects') + [
            'project' => $this->decorate($record),
            // Client-visible updates only, and the ownership check runs again
            // inside this call rather than trusting the one above.
            'updates' => ClientPortal::updates($client, $project),
        ]);
    }

    /**
     * Attach what the views need, and nothing more.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * Note what is NOT attached: the teams on the project, and their members.
     * ProjectDirectory::teamsOf would hand over every employee's name, department
     * and designation — the staff project page shows that, and it should. A
     * client gets the one person they actually deal with, the project manager.
     *
     * This is the kind of thing that leaks by accident: `+ ProjectDirectory::row()`
     * looks like a harmless convenience and quietly publishes the org chart.
     *
     * AND THAT IS EXACTLY WHAT THE ROW NOW CARRIES.
     *
     * ProjectDirectory::row is the staff shape, and its `manager_record` is a
     * full EmployeeDirectory row — name, staff id, email, department,
     * designation, joining date, date of birth. The client needs one field of
     * it. So this REPLACES the key rather than adding one, which is why the
     * override is written as an array merge with `$project` on the right and
     * not as `$project + [...]`: the union operator keeps the left side's value
     * for a key that already exists, and would have silently kept the staff
     * record.
     * ─────────────────────────────────────────────────────────────────────────
     *
     * @param  array<string, mixed>  $project
     * @return array<string, mixed>
     */
    protected function decorate(array $project): array
    {
        $manager = $project['manager_record'] ?? null;

        return array_merge($project, [
            'manager_record' => $manager === null ? null : [
                'name' => $manager['name'],
                // Their role on this project, not their designation, department
                // or staff id. The client needs to know who to ask.
                'designation' => 'Project Manager',
            ],
        ]);
    }
}
