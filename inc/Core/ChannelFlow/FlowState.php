<?php
namespace RankingCoach\Inc\Core\ChannelFlow;

final class FlowState {
    public function __construct(
        public bool $registered = false,
        public bool $emailVerified = false,
        public bool $activated = false,
        public bool $onboarded = false,
        public array $meta = []
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            $data['registered'] ?? false,
            $data['emailVerified'] ?? false,
            $data['activated'] ?? false,
            $data['onboarded'] ?? false,
            $data['meta'] ?? []
        );
    }

    public function toArray(): array {
        return [
            'registered' => $this->registered,
            'emailVerified' => $this->emailVerified,
            'activated' => $this->activated,
            'onboarded' => $this->onboarded,
            'meta' => $this->meta,
        ];
    }
}