# Distributed Notification Architecture Plan

This plan establishes the robust notification flow from the Symfony microservices to the connected clients via the .NET SignalR hub, with the Spring Boot microservice acting as a centralized and secure signing proxy.

## Architecture Flow
1. **Symfony Business Services** (`gearoil`, `stock`) will send domain-specific notifications via HTTP POST to the Spring Boot Notification Service.
2. **Spring Boot Proxy** ([Notification](file:///c:/Users/HP%20ULTRA/Desktop/Notification/Notification/src/main/java/com/example/Notification/model/Notification.java#7-50) service) accepts the HTTP POST, securely signs the payload with its RSA private key, persists the transaction, and proxies it to the `.NET` API Gateway / User Service.
3. **.NET SignalR Hub** (`User_Service`) receives the signed broadcast via a `UserNotificationsController` and dispatches it over WebSockets to front-end clients using SignalR.

## Proposed Changes

### 1. .NET Microservice (`User_Service`)
#### [NEW] [NotificationHub.cs](file:///C:/Users/HP%20ULTRA/gearoil/User_Service/Hubs/NotificationHub.cs)
- Create the SignalR Hub.

#### [NEW] [UserNotificationsController.cs](file:///C:/Users/HP%20ULTRA/gearoil/User_Service/Controllers/UserNotificationsController.cs)
- Expose the `POST /api/UserNotifications/broadcast` endpoint that receives the signed payload and triggers the hub broadcast.

#### [MODIFY] [Program.cs](file:///C:/Users/HP%20ULTRA/gearoil/User_Service/Program.cs)
- Register `builder.Services.AddSignalR()`.
- Map the endpoint `app.MapHub<NotificationHub>("/hubs/notifications")`.

### 2. API Gateway (`APIGateway`) - Optional depending on routing
#### [MODIFY] [Ocelot.json](file:///C:/Users/HP%20ULTRA/gearoil/APIGateway/Ocelot.json)
- Add routes for the WebSocket `/hubs/notifications` so clients can connect locally.

### 3. Symfony Microservices
#### [NEW] [NotificationSenderService.php](file:///C:/Users/HP%20ULTRA/symfony-backend-gearoil/src/Service/NotificationSenderService.php)
- Implement a Symfony service utilizing `symfony/http-client` to POST events directly to the Spring Boot backend (`http://localhost:8080/api/notifications/send`).

#### [NEW] [NotificationSenderService.php](file:///C:/Users/HP%20ULTRA/stock-microservice-symfony/src/Service/NotificationSenderService.php)
- Implement an identical Symfony centralized service.

## Verification Plan

### Automated Tests
- N/A

### Manual Verification
- We will trigger an event from Symfony and observe signalR websocket payloads to ensure events bubble across properly.
