import csv

# The data generated for you
data = [
    {"id": 29384, "name": "Juan dela Cruz", "email": "juan.delacruz@example.com"},
    {"id": 84721, "name": "Maria Clara Santos", "email": "m.clara.santos@example.com"},
    {"id": 10392, "name": "Jose Rizalino Reyes", "email": "jose.reyes@example.com"},
    {"id": 58291, "name": "Angelo Victorio", "email": "angelo.victorio@example.com"},
    {"id": 47203, "name": "Carmela San Jose", "email": "c.sanjose@example.com"},
    {"id": 91023, "name": "Danilo Panganiban", "email": "danilo.pangan@example.com"},
    {"id": 63849, "name": "Elena Guinto", "email": "elena.guinto@example.com"},
    {"id": 22940, "name": "Fernando Poe Jr. Mendoza", "email": "fernando.mendoza@example.com"},
    {"id": 77382, "name": "Gloria Macaraeg", "email": "gloria.macaraeg@example.com"},
    {"id": 15438, "name": "Hernando Silang", "email": "h.silang@example.com"},
    {"id": 88293, "name": "Isabelita Dizon", "email": "isabelita.dizon@example.com"},
    {"id": 33049, "name": "Jaime Sinag", "email": "jaime.sinag@example.com"},
    {"id": 55102, "name": "Kristine Hermosa Bulaon", "email": "kristine.bulaon@example.com"},
    {"id": 49281, "name": "Lamberto Avellana", "email": "lamberto.a@example.com"},
    {"id": 70392, "name": "Manuel Quezon Roxas", "email": "manuel.roxas@example.com"},
    {"id": 11048, "name": "Noemi Tesoro", "email": "noemi.tesoro@example.com"},
    {"id": 94832, "name": "Oscar Alcaraz", "email": "oscar.alcaraz@example.com"},
    {"id": 37281, "name": "Paolo Bonifacio", "email": "paolo.bonifacio@example.com"},
    {"id": 66103, "name": "Quirino Abad", "email": "quirino.abad@example.com"},
    {"id": 82930, "name": "Rosa Rosal Luna", "email": "rosa.luna@example.com"},
    {"id": 54920, "name": "Salvador Panelo Garcia", "email": "s.garcia@example.com"},
    {"id": 19283, "name": "Teresa Magbanua Dimaliwat", "email": "teresa.dimaliwat@example.com"},
    {"id": 44029, "name": "Ursula Tolentino", "email": "ursula.t@example.com"},
    {"id": 99382, "name": "Vicente Manansala", "email": "vicente.manansala@example.com"},
    {"id": 28103, "name": "Wilfredo Ma. Guerrero", "email": "wilfredo.guerrero@example.com"},
    {"id": 73821, "name": "Xavier Lucero", "email": "xavier.lucero@example.com"},
    {"id": 61029, "name": "Yolanda Evangelista", "email": "yolanda.e@example.com"},
    {"id": 38291, "name": "Zenaida Amador", "email": "zenaida.amador@example.com"},
    {"id": 50492, "name": "Benigno Aquino Ramos", "email": "benigno.ramos@example.com"},
    {"id": 12738, "name": "Corazon Cojuangco Agbayani", "email": "corazon.agbayani@example.com"}
]

filename = "filipino_data.csv"
fields = ["id", "name", "email"]

# Writing to the csv file
with open(filename, 'w', newline='', encoding='utf-8') as csvfile:
    writer = csv.DictWriter(csvfile, fieldnames=fields)
    
    # Write the header
    writer.writeheader()
    
    # Write the rows
    writer.writerows(data)

print(f"Successfully created {filename}!")