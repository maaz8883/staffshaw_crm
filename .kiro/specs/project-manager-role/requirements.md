# Requirements Document

## Introduction

This feature introduces a new **Project Manager** role to the CRM. A Project Manager does not belong to a single fixed team the way an Agent or Team Head does. Instead, a Project Manager browses the list of teams across the system and requests to join one or more teams. Joining is not immediate: the Team Head of the target team must approve the request before the Project Manager gains any access to that team's data. A team may have multiple Project Managers joined to it at the same time, and a Project Manager may join multiple teams at the same time (many-to-many relationship). Once a join request is approved, the Project Manager gains scoped access to that team's sales (view + create), invoices (view + generate), and brief submissions (view only), and sees that team's data on their dashboard. A Project Manager can leave a joined team at any time, with no limit on the number of teams they may join. This feature does not modify the existing Admin, Manager, Agent, PPC, Team Head, or Sub-Team Head roles or their current access behavior, other than granting Team Heads the additional ability to approve or reject Project Manager join requests for the team(s) they head.

## Glossary

- **Project_Manager**: A user whose Role.name is "Project Manager". Gains access to team data only through joined teams rather than a fixed team_id assignment.
- **Team_Membership**: An association record linking a Project_Manager to a Team, carrying a status of "pending", "approved", or "rejected". Distinct from the existing `users.team_id` single-team assignment used by Agent/Team Head/Sub-Team Head/PPC users.
- **Join_Request**: A Team_Membership record with status "pending", created when a Project_Manager asks to join a Team and awaiting a decision from that Team's Team Head.
- **Joined_Team**: A Team for which a Team_Membership record with status "approved" exists for a given Project_Manager.
- **Team_Head**: The user referenced by a Team's team_head_id, who has authority to approve or reject Join_Requests for that Team.
- **System**: The CRM application backend (controllers, models, and supporting services).
- **Role_Registry**: The set of role names defined in the Role model and seeded into the roles table.
- **Sale**: An existing Sale record, associated with a team_id and user_id.
- **Invoice**: An existing Invoice record, associated with a Sale.
- **Brief_Submission**: An existing BriefSubmission record, associated with a Sale.
- **Dashboard**: The role-specific admin dashboard view rendered by DashboardController.
- **Team_Directory**: The list of all Teams in the system (across all companies) presented to a Project_Manager for browsing and joining.

## Requirements

### Requirement 1: Project Manager Role Definition

**User Story:** As an Admin, I want a distinct "Project Manager" role available in the system, so that I can assign users to this role without altering the behavior of existing roles.

#### Acceptance Criteria

1. THE Role_Registry SHALL include a role named "Project Manager" in addition to the existing Admin, Manager, Agent, and PPC roles.
2. THE System SHALL preserve the existing access behavior of the Admin, Manager, Agent, PPC, Team Head, and Sub-Team Head roles unchanged after introducing the Project Manager role.
3. WHERE a user's Role.name is "Project Manager", THE System SHALL NOT require that user to have a value in the users.team_id column in order to function correctly.
4. THE System SHALL allow an Admin to assign the Project Manager role to a user through the existing user management interface.

### Requirement 2: Team Directory Browsing

**User Story:** As a Project Manager, I want to see a list of available teams, so that I can choose which teams to join.

#### Acceptance Criteria

1. WHEN a Project_Manager requests the Team_Directory, THE System SHALL return all Teams across all companies in the system.
2. THE Team_Directory SHALL display, for each Team, at minimum the team name and the company the team belongs to.
3. THE Team_Directory SHALL indicate, for each Team, whether the requesting Project_Manager has already joined that Team.
4. IF a user who does not have the Project Manager role requests the Team_Directory join/browse feature, THEN THE System SHALL deny access to that feature.

### Requirement 3: Requesting to Join a Team

**User Story:** As a Project Manager, I want to request to join a team from the team directory, so that the team's Team Head can decide whether to grant me access.

#### Acceptance Criteria

1. WHEN a Project_Manager selects a Team from the Team_Directory to join, THE System SHALL create a Join_Request (Team_Membership with status "pending") linking that Project_Manager to that Team.
2. WHEN a Join_Request is created, THE System SHALL NOT grant the Project_Manager any access to that Team's scoped data until the Join_Request is approved.
3. THE System SHALL allow a Project_Manager to hold Team_Memberships (pending or approved) in more than one Team at the same time.
4. THE System SHALL allow a Team to have Team_Memberships with more than one Project_Manager at the same time.
5. IF a Project_Manager attempts to request joining a Team for which a "pending" or "approved" Team_Membership already exists, THEN THE System SHALL leave the existing Team_Membership unchanged and SHALL NOT create a duplicate Team_Membership.
6. IF a Project_Manager requests to join a Team for which their prior Team_Membership has status "rejected", THEN THE System SHALL allow a new Join_Request to be created, resetting the status to "pending".
7. THE System SHALL NOT impose a maximum number of Teams a Project_Manager may hold Team_Memberships in.
8. THE Team_Directory SHALL indicate, for each Team, the Project_Manager's current Team_Membership status with that Team ("not joined", "pending", "approved", or "rejected").

### Requirement 4: Team Head Approval of Join Requests

**User Story:** As a Team Head, I want to review and approve or reject Project Manager join requests for my team, so that I control who gains access to my team's data.

#### Acceptance Criteria

1. WHEN a Team_Head views their pending Join_Requests, THE System SHALL display only Join_Requests for Teams where that user is the team_head_id.
2. WHEN a Team_Head approves a Join_Request, THE System SHALL update the Team_Membership status to "approved" and SHALL immediately grant the Project_Manager access to that Team's scoped data described in Requirements 6, 7, and 8.
3. WHEN a Team_Head rejects a Join_Request, THE System SHALL update the Team_Membership status to "rejected" and SHALL NOT grant the Project_Manager any access to that Team's data.
4. IF a user who is not the Team_Head of the Team referenced in a Join_Request attempts to approve or reject that Join_Request, THEN THE System SHALL deny the action.
5. THE System SHALL notify the Team_Head when a new Join_Request is created for a Team they head, consistent with the existing notification mechanism used elsewhere in the System.
6. WHERE a Team has no team_head_id assigned, THE System SHALL allow an Admin or Manager to approve or reject Join_Requests for that Team.

### Requirement 5: Leaving a Team

**User Story:** As a Project Manager, I want to leave a team I previously joined, so that I no longer see or manage that team's data.

#### Acceptance Criteria

1. WHEN a Project_Manager requests to leave a Joined_Team (an "approved" Team_Membership), THE System SHALL remove the corresponding Team_Membership without requiring approval from any other user.
2. WHEN a Team_Membership is removed, THE System SHALL immediately revoke the Project_Manager's scoped access to that Team's data described in Requirements 6, 7, and 8.
3. IF a Project_Manager requests to leave a Team for which no "approved" Team_Membership exists, THEN THE System SHALL return an error indicating the Project_Manager has not joined that Team.
4. WHEN a Project_Manager cancels a "pending" Join_Request before it is decided, THE System SHALL remove that Team_Membership.

### Requirement 6: Scoped Sales Access

**User Story:** As a Project Manager, I want to view and create sales for the teams I have joined, so that I can manage sales activity for those teams.

#### Acceptance Criteria

1. WHEN a Project_Manager views the sales list, THE System SHALL display only Sales whose team_id matches one of the Project_Manager's Joined_Teams.
2. WHEN a Project_Manager views the sales list, THE System SHALL exclude Sales belonging to Teams the Project_Manager has not joined.
3. WHEN a Project_Manager creates a new Sale, THE System SHALL require the Project_Manager to select a Team from among their currently Joined_Teams as the Sale's team_id.
4. IF a Project_Manager attempts to create a Sale for a Team that is not one of their Joined_Teams, THEN THE System SHALL reject the request.
5. IF a Project_Manager attempts to view a specific Sale belonging to a Team that is not one of their Joined_Teams, THEN THE System SHALL deny access to that Sale.
6. THE System SHALL NOT allow a Project_Manager to approve, reject, or delete any Sale.
7. WHERE a Project_Manager is also the creator (user_id) of a pending Sale in a Joined_Team, THE System SHALL allow that Project_Manager to edit the Sale while it remains pending approval, consistent with the edit rule applied to Agents.

### Requirement 7: Scoped Invoice Access

**User Story:** As a Project Manager, I want to view and generate invoices for sales in the teams I have joined, so that I can support billing activity for those teams.

#### Acceptance Criteria

1. WHEN a Project_Manager views the invoices list, THE System SHALL display only Invoices whose related Sale's team_id matches one of the Project_Manager's Joined_Teams.
2. WHEN a Project_Manager views the invoices list, THE System SHALL exclude Invoices whose related Sale belongs to a Team the Project_Manager has not joined.
3. WHEN a Project_Manager generates an Invoice for a Sale belonging to one of their Joined_Teams, THE System SHALL create the Invoice using the same generation rules applied to other non-admin roles.
4. IF a Project_Manager attempts to generate or view an Invoice for a Sale belonging to a Team that is not one of their Joined_Teams, THEN THE System SHALL deny the request.
5. THE System SHALL NOT allow a Project_Manager to void any Invoice.

### Requirement 8: Scoped Brief Submission Access

**User Story:** As a Project Manager, I want to view brief submissions for sales in the teams I have joined, so that I can monitor brief completion status for those teams.

#### Acceptance Criteria

1. WHEN a Project_Manager views brief submissions associated with a Sale, THE System SHALL allow this only when the Sale's team_id matches one of the Project_Manager's Joined_Teams.
2. IF a Project_Manager attempts to view brief submissions for a Sale belonging to a Team that is not one of their Joined_Teams, THEN THE System SHALL deny access.
3. THE System SHALL NOT allow a Project_Manager to submit, edit, or delete a Brief_Submission.

### Requirement 9: Scoped Dashboard View

**User Story:** As a Project Manager, I want my dashboard to show data for the teams I have joined, so that I can monitor performance without seeing unrelated data.

#### Acceptance Criteria

1. WHEN a Project_Manager without any Joined_Team loads the Dashboard, THE System SHALL display a Dashboard view indicating no teams have been joined yet.
2. WHEN a Project_Manager with at least one Joined_Team loads the Dashboard, THE System SHALL display sales statistics, revenue, and target achievement data scoped to only their Joined_Teams.
3. WHEN a Project_Manager with at least one Joined_Team loads the Dashboard, THE System SHALL display the members list for each Joined_Team.
4. THE Dashboard SHALL exclude data belonging to Teams the Project_Manager has not joined.
5. THE Dashboard SHALL exclude company-wide or system-wide administrative statistics from the Project_Manager's Dashboard view.

### Requirement 10: Access Restriction Outside Joined Teams

**User Story:** As an Admin, I want Project Managers restricted to only their joined teams' data, so that sensitive data from other teams and companies remains protected.

#### Acceptance Criteria

1. IF a Project_Manager attempts to access a route or resource restricted to Admin or Manager roles (such as company management, settings, or user management), THEN THE System SHALL deny access.
2. IF a Project_Manager has zero Joined_Teams (zero "approved" Team_Memberships), THEN THE System SHALL return empty results for sales, invoices, and brief submission queries scoped by team.
3. THE System SHALL evaluate a Project_Manager's data access strictly based on their current "approved" Team_Membership records at the time of each request, without caching stale membership state across requests.
4. THE System SHALL NOT grant a Project_Manager access to a Team's data while their Team_Membership for that Team has status "pending" or "rejected".
