<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 200);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
        Schema::create('dramas', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->text('synopsis')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('cover_url', 2048)->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->boolean('is_playable')->default(false);
            $table->unsignedInteger('heat')->default(0);
            $table->timestamps();
        });
        Schema::create('story_characters', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('cover_url', 2048)->nullable();
            $table->timestamps();
        });
        foreach (['drama_tag' => ['drama_id', 'dramas'], 'story_character_tag' => ['story_character_id', 'story_characters']] as $pivot => [$column, $parent]) {
            Schema::create($pivot, function (Blueprint $table) use ($column, $parent): void {
                $table->foreignId($column);
                $table->foreignId('tag_id');
                $table->primary([$column, 'tag_id']);
                $table->foreign($column)->references('id')->on($parent)->cascadeOnDelete();
                $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['story_character_tag', 'drama_tag', 'story_characters', 'dramas', 'tags'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
