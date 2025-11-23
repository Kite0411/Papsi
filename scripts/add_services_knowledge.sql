-- Add service-related knowledge to the chatbot
-- Run these SQL commands in phpMyAdmin or your database tool

-- Service Information (Dynamic responses that work with database)
INSERT INTO chat_knowledge (trigger1, response) VALUES 
('what services do you offer', 'I\'ll check our current services for you. Let me get the latest information from our database.'),

('what are your services', 'I\'ll show you our current services. Let me retrieve the latest information.'),

('services available', 'I\'ll get our current services for you. Let me check what we offer.'),

('what services are available', 'I\'ll show you our available services. Let me get the current information.'),

('list of services', 'I\'ll list our current services for you. Let me retrieve the information.'),

('show me your services', 'I\'ll show you our current services. Let me get the latest information.'),

('available services', 'I\'ll get our available services for you. Let me check what we offer.'),

('what do you offer', 'I\'ll show you what we offer. Let me get our current services.'),

('services', 'I\'ll show you our services. Let me get the current information.'),

('help', 'I can help you with vehicle diagnostics and service recommendations. What problem are you having with your vehicle?'),

('hi', 'Hello! I\'m your vehicle diagnostic assistant. I can help you with vehicle problems and service recommendations. What problem are you experiencing?'),

('hello', 'Hi there! I\'m here to help with your vehicle problems. I can recommend services and help with diagnostics. What can I help you with?'),

('prices', 'I\'ll check our current service prices for you. Let me get the latest information.'),

('service prices', 'I\'ll show you our current service prices. Let me retrieve the information.'),

('how much', 'I\'ll check our current prices for you. Let me get the latest service information.'),

('cost', 'I\'ll show you our current service costs. Let me get the information.'),

('price', 'I\'ll check our current prices for you. Let me get the service information.'),

('book appointment', 'You can book an appointment by calling us at +63 912 345 6789 or visiting our reservation page. What service would you like to schedule?'),

('make appointment', 'To make an appointment, call us at +63 912 345 6789 or use our online booking system. What service do you need?'),

('schedule', 'You can schedule by calling +63 912 345 6789 or using our online booking. What service would you like to book?'),

('appointment', 'To book an appointment, call us at +63 912 345 6789 or visit our reservation page. What service do you need?'),

('business hours', 'We\'re open Monday to Saturday, 7 AM to 7 PM. Closed on Sundays. You can book appointments during these hours.'),

('hours', 'Our business hours: Monday to Saturday, 7 AM to 7 PM. Closed on Sundays.'),

('location', 'We\'re located at 123 Auto Street, Manila, Philippines. Easy to find and with plenty of parking!'),

('address', 'Our address: 123 Auto Street, Manila, Philippines. We have plenty of parking available.'),

('phone number', 'You can reach us at +63 912 345 6789. We\'re available during business hours to help with your vehicle problems.'),

('contact', 'Contact us at +63 912 345 6789 or visit us at 123 Auto Street, Manila, Philippines. We\'re open Monday to Saturday, 7 AM to 7 PM.');
